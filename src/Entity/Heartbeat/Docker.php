<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Helper\System;

/**
 * Class Docker
 *
 * Reports whether Docker Engine is installed, the version the daemon is running, and how many
 * containers it has. A host running containers is one where the MySQL / PHP / Node versions
 * reported for the host may not be the ones the application actually runs on.
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Docker implements \JsonSerializable
{
    /**
     * How long a wedged docker CLI or dockerd may stall the heartbeat
     *
     * @var int
     */
    private const EXEC_TIMEOUT = 5;

    /**
     * Daemon binaries to look for, in preference order
     *
     * @var array<int, string>
     */
    private const DAEMONS = [
        '/usr/bin/dockerd',
        '/usr/sbin/dockerd',
        '/usr/local/bin/dockerd',
    ];

    /**
     * Client binaries to look for, in preference order. The client is what asks the running
     * daemon for its version and container counts.
     *
     * @var array<int, string>
     */
    private const CLIENTS = [
        '/usr/bin/docker',
        '/usr/local/bin/docker',
    ];

    /**
     * dpkg package names that mean Docker Engine is installed: Docker's own repository, Ubuntu's
     * archive, and the Moby build some providers ship.
     *
     * @var array<int, string>
     */
    private const PACKAGES = [
        'docker-ce',
        'docker.io',
        'moby-engine',
    ];

    /**
     * Gathers the Docker Engine version and container counts, if Docker is installed
     *
     * A missing Docker is reported as `present: false` — it must never throw, because that would
     * abort the whole heartbeat.
     *
     * @return array<string, mixed>|null
     */
    public function get(): ?array
    {
        switch (Os::getType()) {
            case Os::LINUX:
                return $this->collect();

            case Os::MACOS:
                return null;

            default:
                throw new HeartbeatException('Unable to determine Docker status.');
        }
    }

    /**
     * Parses a `dockerd --version` banner, e.g. `Docker version 27.3.1, build 41ca978`
     *
     * @param string $sBanner The first line of `--version` output
     *
     * @return array{version: string, version_full: string}|null
     */
    public static function parseBanner(string $sBanner): ?array
    {
        if (!preg_match('/\bversion\s+([^\s,]+)/i', $sBanner, $aMatch)) {
            return null;
        }

        return self::parseVersion($aMatch[1]);
    }

    /**
     * Strips a Debian epoch and takes the upstream x.y.z from a dpkg version, e.g.
     * `5:27.3.1-1~ubuntu.24.04~noble` or `24.0.7-0ubuntu4.1`
     *
     * @param string $sRaw The Version field from dpkg-query
     *
     * @return array{version: string, version_full: string}|null
     */
    public static function parsePackageVersion(string $sRaw): ?array
    {
        return self::parseVersion(preg_replace('/^\d+:/', '', trim($sRaw)) ?? $sRaw);
    }

    /**
     * Parses the `ServerVersion|ContainersRunning|Containers` line `docker info` is asked for
     *
     * Any part that is not what was asked for comes back `null` — unknown, never zero.
     *
     * @param string $sLine The single line of formatted `docker info` output
     *
     * @return array{version: array{version: string, version_full: string}|null, running: int|null, total: int|null}
     */
    public static function parseInfo(string $sLine): array
    {
        $aParts   = array_map('trim', explode('|', trim($sLine)));
        $aVersion = self::parseVersion($aParts[0] ?? '');

        //  With no daemon to ask, the CLI still renders the template — `|0|0` — so counts that
        //  arrive without a server version are a failed call, not an idle daemon
        if ($aVersion === null) {
            return ['version' => null, 'running' => null, 'total' => null];
        }

        return [
            'version' => $aVersion,
            'running' => self::count($aParts[1] ?? null),
            'total'   => self::count($aParts[2] ?? null),
        ];
    }

    /**
     * @return array{version: string, version_full: string}|null
     */
    private static function parseVersion(string $sFull): ?array
    {
        $sFull = ltrim(trim($sFull), 'vV');

        if (!preg_match('/^(\d+\.\d+\.\d+)/', $sFull, $aMatch)) {
            return null;
        }

        return [
            'version'      => $aMatch[1],
            'version_full' => $sFull,
        ];
    }

    private static function count(?string $sValue): ?int
    {
        return $sValue !== null && preg_match('/^\d+$/', $sValue) ? (int) $sValue : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function collect(): array
    {
        $sDaemon  = $this->findBinary(self::DAEMONS);
        $sClient  = $this->findBinary(self::CLIENTS);
        $aPackage = $this->findPackage();

        if ($sDaemon === null && $aPackage === null) {
            return $this->reported(false, null, null, null, null, null);
        }

        $sState  = $this->daemonState();
        $aErrors = [];

        //  The running daemon is the best answer — it is what containers are actually on. The
        //  daemon binary and the package are what would run after a restart, which is usually the
        //  same thing, so they stand in when the daemon cannot be asked.
        $aInfo = $sClient !== null ? $this->infoFromDaemon($sClient, $aErrors) : null;

        if ($aInfo !== null && $aInfo['version'] !== null) {
            return $this->reported(true, $aInfo['version'] + ['version_source' => 'daemon'], $aPackage, $sState, $aInfo, null);
        }

        $aVersion = $sDaemon !== null ? $this->versionFromBinary($sDaemon, $aErrors) : null;

        if ($aVersion !== null) {
            $aVersion += ['version_source' => 'binary'];
        } elseif ($aPackage !== null && $aPackage['version'] !== null) {
            $aVersion = $aPackage['version'] + ['version_source' => 'package'];
        } else {
            $aErrors[] = 'Docker is installed but its version could not be read';
        }

        return $this->reported(true, $aVersion, $aPackage, $sState, $aInfo, $aVersion === null ? implode('; ', $aErrors) : null);
    }

    /**
     * @param array<string, string>|null                                                                $aVersion
     * @param array{name: string, version: array<string, string>|null}|null                            $aPackage
     * @param array{version: array<string, string>|null, running: int|null, total: int|null}|null $aInfo
     *
     * @return array<string, mixed>
     */
    private function reported(bool $bPresent, ?array $aVersion, ?array $aPackage, ?string $sState, ?array $aInfo, ?string $sError): array
    {
        return [
            'present'        => $bPresent,
            'version'        => $aVersion['version'] ?? null,
            'version_full'   => $aVersion['version_full'] ?? null,
            'version_source' => $aVersion['version_source'] ?? null,
            'package'        => $aPackage['name'] ?? null,
            'daemon'         => $sState,
            'containers'     => $bPresent ? [
                'running' => $aInfo['running'] ?? null,
                'total'   => $aInfo['total'] ?? null,
            ] : null,
            'error'          => $sError,
        ];
    }

    /**
     * @param array<int, string> $aCandidates
     */
    private function findBinary(array $aCandidates): ?string
    {
        foreach ($aCandidates as $sPath) {
            if (is_file($sPath) && is_executable($sPath)) {
                return $sPath;
            }
        }

        return null;
    }

    /**
     * The systemd state of the docker unit (`active`, `inactive`, `failed`, …), or null when it
     * cannot be asked
     */
    private function daemonState(): ?string
    {
        if (!System::commandExists('systemctl')) {
            return null;
        }

        //  is-active exits non-zero for anything but `active`, but still prints the state
        $aOutput = [];

        try {
            System::exec('systemctl is-active docker 2>/dev/null', $aOutput);
        } catch (CommandFailedException) {
        }

        $sState = trim((string) reset($aOutput));

        return preg_match('/^[a-z-]+$/', $sState) ? $sState : null;
    }

    /**
     * @param array<int, string> $aErrors
     *
     * @return array{version: array{version: string, version_full: string}|null, running: int|null, total: int|null}|null
     */
    private function infoFromDaemon(string $sClient, array &$aErrors): ?array
    {
        if (!$this->isTrusted($sClient)) {
            $aErrors[] = 'Not executed: ' . $sClient . ' is not owned exclusively by root';
            return null;
        }

        try {
            $sLine = System::execString(
                $this->bounded(escapeshellarg($sClient) . ' info --format ' . escapeshellarg('{{.ServerVersion}}|{{.ContainersRunning}}|{{.Containers}}') . ' 2>/dev/null')
            );
        } catch (CommandFailedException) {
            $aErrors[] = 'The Docker daemon did not answer';
            return null;
        }

        return self::parseInfo($sLine);
    }

    /**
     * @param array<int, string> $aErrors
     *
     * @return array{version: string, version_full: string}|null
     */
    private function versionFromBinary(string $sPath, array &$aErrors): ?array
    {
        if (!$this->isTrusted($sPath)) {
            $aErrors[] = 'Not executed: ' . $sPath . ' is not owned exclusively by root';
            return null;
        }

        try {
            $sBanner = trim(System::execString($this->bounded(escapeshellarg($sPath) . ' --version 2>/dev/null')));
        } catch (CommandFailedException) {
            $aErrors[] = 'Failed to read the version from ' . $sPath;
            return null;
        }

        return self::parseBanner($sBanner);
    }

    /**
     * @return array{name: string, version: array{version: string, version_full: string}|null}|null
     */
    private function findPackage(): ?array
    {
        if (!System::commandExists('dpkg-query')) {
            return null;
        }

        foreach (self::PACKAGES as $sPackage) {
            try {
                $sLine = System::execString(
                    'dpkg-query -W -f=\'${Package}\t${Status}\t${Version}\' ' . escapeshellarg($sPackage) . ' 2>/dev/null'
                );
            } catch (CommandFailedException) {
                continue;
            }

            $aParts = explode("\t", $sLine);

            if (count($aParts) < 3 || !str_contains($aParts[1], 'install ok installed')) {
                continue;
            }

            return [
                'name'    => $aParts[0],
                'version' => self::parsePackageVersion($aParts[2]),
            ];
        }

        return null;
    }

    /**
     * The environment is emptied so an inherited DOCKER_HOST cannot point the client at another
     * daemon, and the call is bounded so a wedged daemon cannot stall the beat.
     */
    private function bounded(string $sCommand): string
    {
        if (System::commandExists('env')) {
            $sCommand = 'env -i ' . $sCommand;
        }

        if (System::commandExists('timeout')) {
            $sCommand = 'timeout ' . self::EXEC_TIMEOUT . ' ' . $sCommand;
        }

        return $sCommand;
    }

    /**
     * Both the path as given and the path it resolves to are checked, since a
     * symlink is only as trustworthy as its target.
     */
    private function isTrusted(string $sPath): bool
    {
        $sReal = realpath($sPath);

        if ($sReal === false) {
            return false;
        }

        return $this->isRootOwnedPath($sPath) && ($sReal === $sPath || $this->isRootOwnedPath($sReal));
    }

    /**
     * Root must own every component, and none of them may be group- or other-writable
     */
    private function isRootOwnedPath(string $sPath): bool
    {
        if (!str_starts_with($sPath, '/')) {
            return false;
        }

        $sCurrent    = '';
        $aComponents = ['/'];

        foreach (explode('/', trim($sPath, '/')) as $sComponent) {
            if ($sComponent === '') {
                continue;
            }

            $sCurrent      = $sCurrent . '/' . $sComponent;
            $aComponents[] = $sCurrent;
        }

        foreach ($aComponents as $sComponent) {
            $iOwner = @fileowner($sComponent);
            $iPerms = @fileperms($sComponent);

            if ($iOwner === false || $iPerms === false) {
                return false;
            }

            if ($iOwner !== 0) {
                return false;
            }

            if ($iPerms & 0022) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
}
