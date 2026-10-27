<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Helper\Redact;
use Shed\Cli\Helper\System;

/**
 * Class Sudoers
 *
 * Reports who has sudo access and what they are allowed to run: the privilege
 * specifications in /etc/sudoers and every file in /etc/sudoers.d, plus the
 * resolved membership of any group one of those lines grants access to (a
 * `%group` line names a group, not the people in it).
 *
 * Mirrors Cron's approach throughout: a permissions failure is reported as an
 * explicit `error`, never as an absence of entries, and a file this collector
 * cannot make sense of is skipped line-by-line rather than discarded or
 * thrown over.
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Sudoers implements \JsonSerializable
{
    /**
     * The primary sudoers file. Mode 0440 root:root on a stock install, so
     * reading it requires root.
     *
     * @var string
     */
    private const SUDOERS = '/etc/sudoers';

    /**
     * Drop-in directory for packaged/host-specific grants.
     *
     * @var string
     */
    private const SUDOERS_D = '/etc/sudoers.d';

    /**
     * One privilege specification: `subject host=(run_as) [tags:] commands`.
     * Aliases (`User_Alias`, `Cmnd_Alias`, ...) are recognised only well enough
     * to be skipped, never expanded — a line naming one is reported with the
     * alias name verbatim in `commands`, not what it resolves to.
     *
     * @var string
     */
    private const ENTRY = '/^(\S+)\s+(\S+)\s*=\s*(?:\(([^)]*)\)\s*)?((?:(?:NOPASSWD|PASSWD|NOEXEC|EXEC|SETENV|NOSETENV):\s*)*)(.+)$/';

    /**
     * Lines which are never a privilege specification, even though they are
     * not comments: Defaults tuning, alias definitions, and include
     * directives (both the `#include`/`#includedir` form and the newer
     * `@include`/`@includedir` form). /etc/sudoers.d is already read directly
     * below, so a directive naming it is simply skipped rather than followed.
     *
     * @var string
     */
    private const SKIP = '/^(Defaults\b|User_Alias\b|Runas_Alias\b|Host_Alias\b|Cmnd_Alias\b|#include|@include)/';

    /**
     * Set when /etc/sudoers.d could not even be listed. A single unreadable
     * file inside it is instead recorded against that file's own entry.
     *
     * @var string|null
     */
    private ?string $sError = null;

    // --------------------------------------------------------------------------

    /**
     * Gathers who is listed as sudoers and what they may run
     *
     * @return array|null
     */
    public function get(): ?array
    {
        switch (Os::getType()) {
            case Os::LINUX:
                $aFiles = $this->getFiles();

                return [
                    'groups' => (object) $this->getGroupMembership($aFiles),
                    'files'  => (object) $aFiles,
                    'error'  => $this->sError,
                ];

            case Os::MACOS:
                return null; // not a deploy target; sudo's grants are not what's being audited here

            default:
                throw new HeartbeatException('Unable to determine sudoers status.');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Reads /etc/sudoers and every file in /etc/sudoers.d
     *
     * @return array<string, array{entries: array, error: ?string}>
     */
    private function getFiles(): array
    {
        $aFiles = [];
        $aFiles[self::SUDOERS] = $this->readFile(self::SUDOERS);

        if (!is_readable(self::SUDOERS_D)) {
            $this->sError = 'Access to ' . self::SUDOERS_D . ' denied - insufficient permissions (root required)';

            return $aFiles;
        }

        $aEntries = scandir(self::SUDOERS_D);
        if ($aEntries === false) {
            $this->sError = 'Failed to list ' . self::SUDOERS_D;

            return $aFiles;
        }

        foreach ($aEntries as $sFile) {

            $sPath = self::SUDOERS_D . '/' . $sFile;

            //  sudo itself ignores drop-ins with a dot in the name or a trailing
            //  ~ (editor backups), the same convention run-parts and cron.d use
            if ($sFile === '.' || $sFile === '..' || str_contains($sFile, '.') || str_ends_with($sFile, '~') || !is_file($sPath)) {
                continue;
            }

            $aFiles[$sPath] = $this->readFile($sPath);
        }

        return $aFiles;
    }

    // --------------------------------------------------------------------------

    /**
     * Reads and parses one sudoers source file
     *
     * @param string $sPath The file to read
     *
     * @return array{entries: array, error: ?string}
     */
    private function readFile(string $sPath): array
    {
        if (!is_readable($sPath)) {
            return [
                'entries' => [],
                'error'   => 'Access to ' . $sPath . ' denied - insufficient permissions (root required)',
            ];
        }

        $sContents = @file_get_contents($sPath);
        if ($sContents === false) {
            return [
                'entries' => [],
                'error'   => 'Failed to read ' . $sPath,
            ];
        }

        return [
            'entries' => $this->parseSudoers($sContents),
            'error'   => null,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Parses the privilege specifications out of a sudoers file's contents
     *
     * Anything this does not recognise — a Defaults line, an alias
     * definition, an include directive, or a line sudo's own grammar allows
     * that this does not — is skipped rather than guessed at. sudo itself is
     * the only thing that needs to parse this file correctly; this is a
     * best-effort inventory of it.
     *
     * @param string $sContents The file's contents
     *
     * @return array
     */
    public function parseSudoers(string $sContents): array
    {
        $aEntries = [];

        foreach (preg_split('/\r?\n/', $sContents) ?: [] as $sLine) {

            $sLine = trim($sLine);

            if ($sLine === '' || str_starts_with($sLine, '#') || preg_match(self::SKIP, $sLine)) {
                continue;
            }

            if (!preg_match(self::ENTRY, $sLine, $aMatches)) {
                continue;
            }

            $aEntries[] = [
                'subject'  => $aMatches[1],
                'host'     => $aMatches[2],
                'run_as'   => $aMatches[3] !== '' ? $aMatches[3] : null,
                'nopasswd' => stripos($aMatches[4], 'NOPASSWD') !== false,
                'commands' => array_map(
                    static fn (string $sCommand) => Redact::secrets(trim($sCommand)),
                    array_filter(array_map('trim', explode(',', $aMatches[5])))
                ),
            ];
        }

        return $aEntries;
    }

    // --------------------------------------------------------------------------

    /**
     * Resolves the current membership of every `%group` named by an entry
     *
     * A `%group` line grants access to whoever is in that group right now, so
     * reporting the line alone would not answer "who". `getent` failing for a
     * name that was definitely in a sudoers file (a group since deleted) is
     * reported as `null` — unknown, not "nobody" — the same distinction
     * `AptReport` and `NodeReport` draw for their own counts.
     *
     * @param array<string, array{entries: array}> $aFiles
     *
     * @return array<string, array|null>
     */
    private function getGroupMembership(array $aFiles): array
    {
        $aGroupNames = [];

        foreach ($aFiles as $aFile) {
            foreach ($aFile['entries'] as $aEntry) {
                if (str_starts_with($aEntry['subject'], '%')) {
                    $aGroupNames[substr($aEntry['subject'], 1)] = true;
                }
            }
        }

        $aGroups = [];

        foreach (array_keys($aGroupNames) as $sGroup) {
            $aGroups[$sGroup] = $this->getGroupMembers($sGroup);
        }

        return $aGroups;
    }

    // --------------------------------------------------------------------------

    /**
     * @param string $sGroup The group to look up
     *
     * @return string[]|null
     */
    private function getGroupMembers(string $sGroup): ?array
    {
        try {

            //  getent's colon-separated fields: name:password:gid:member,list
            $sLine = System::execString('getent group ' . escapeshellarg($sGroup));
            $aParts = explode(':', $sLine, 4);
            $sMembers = trim($aParts[3] ?? '');

            return $sMembers === '' ? [] : explode(',', $sMembers);

        } catch (CommandFailedException) {
            return null;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * @return array|null
     */
    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
}
