<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Helper\System;

/**
 * Class Cron
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Cron implements \JsonSerializable
{
    /**
     * Where per-user crontabs are stored. Only root may list this directory.
     *
     * @var string
     */
    private const SPOOL = '/var/spool/cron/crontabs';

    /**
     * The system crontab, which carries an additional user column
     *
     * @var string
     */
    private const SYSTEM_CRONTAB = '/etc/crontab';

    /**
     * Drop-in directory for packaged jobs, also carrying a user column
     *
     * @var string
     */
    private const CRON_D = '/etc/cron.d';

    /**
     * The run-parts directories, keyed by the interval they run at
     *
     * @var array
     */
    private const RUN_PARTS = [
        'hourly'  => '/etc/cron.hourly',
        'daily'   => '/etc/cron.daily',
        'weekly'  => '/etc/cron.weekly',
        'monthly' => '/etc/cron.monthly',
    ];

    /**
     * The names cron's daemon goes by, in order of likelihood on our images
     *
     * @var array
     */
    private const DAEMONS = ['cron', 'crond'];

    /**
     * The states systemd reports for a unit.
     *
     * `is-active` is called with stderr merged so a non-zero exit doesn't throw,
     * which means its error output arrives on the same channel as a real status.
     * Anything not in this list is therefore a message rather than a state — on a
     * host without systemd it is "System has not been booted with systemd...".
     *
     * @var array
     */
    private const DAEMON_STATES = [
        'active',
        'inactive',
        'failed',
        'activating',
        'deactivating',
        'reloading',
    ];

    /**
     * The shorthand schedules cron accepts in place of a five field spec
     *
     * @var string
     */
    private const SPECIAL = '@(?:reboot|yearly|annually|monthly|weekly|daily|midnight|hourly)';

    /**
     * Patterns for credentials which commonly appear inline in cron commands
     *
     * @var array
     */
    private const SECRETS = [
        //  scheme://user:password@host
        '/([a-zA-Z][a-zA-Z0-9+.\-]*:\/\/[^:\/\s]+:)([^@\s]+)(?=@)/',
        //  --password=secret, --password secret
        '/(--(?:password|pass|secret|token|api-key)[=\s]+)(\S+)/i',
        //  MYSQL_PASSWORD=secret, S3_ACCESS_SECRET=secret, API_TOKEN=secret
        '/(\b\w*(?:PASS|PASSWD|PASSWORD|SECRET|TOKEN|API_KEY|ACCESS_KEY|CREDENTIAL)\w*\s*=\s*)(\S+)/i',
        //  Authorization headers
        '/((?:Bearer|Basic)\s+)([A-Za-z0-9._\-=+\/]+)/i',
    ];

    /**
     * Attaching the password to `-p` is a MySQL family idiom, so the pattern is
     * only applied to commands which invoke one of its clients. Matching it
     * everywhere mangles unrelated arguments — `run-parts`, which appears in
     * /etc/crontab on a stock Ubuntu install, becomes `run-p[REDACTED]`.
     *
     * The leading (?<![\w\-]) keeps it to a standalone argument, so neither
     * `--password=x` nor a hyphenated word is touched, and a bare `-p` survives.
     *
     * @var string
     */
    private const SECRET_MYSQL_PASSWORD = '/(?<![\w\-])(-p)(?=\S)(\S+)/';

    /**
     * The MySQL clients which accept a password attached to -p
     *
     * @var string
     */
    private const MYSQL_CLIENTS = '/\bmysql(?:dump|admin|show|check|import|_upgrade)?\b/i';

    // --------------------------------------------------------------------------

    /**
     * Gathers details about the cron daemon and the jobs configured on the host
     *
     * @return array|null
     */
    public function get(): ?array
    {
        switch (Os::getType()) {
            case Os::LINUX:
                return [
                    'daemon'    => $this->getDaemonStatus(),
                    'users'     => $this->getUserJobs(),
                    'system'    => $this->getSystemJobs(),
                    'run_parts' => $this->getRunParts(),
                ];

            case Os::MACOS:
                return null; // macOS stores crontabs elsewhere and isn't a deploy target

            default:
                throw new HeartbeatException('Unable to determine cron status.');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the cron daemon is running
     *
     * @return string|null
     */
    private function getDaemonStatus(): ?string
    {
        foreach (self::DAEMONS as $sDaemon) {
            try {

                //  `is-active` exits non-zero when a unit is inactive, which would
                //  otherwise surface as a CommandFailedException and hide the status
                $sStatus = trim(
                    System::execString('systemctl is-active ' . escapeshellarg($sDaemon) . ' 2>&1 || true')
                );

                if (in_array($sStatus, self::DAEMON_STATES, true)) {
                    return $sStatus;
                }

            } catch (CommandFailedException) {
            }
        }

        //  No usable answer from systemd; fall back to looking for the process
        foreach (self::DAEMONS as $sDaemon) {
            try {

                $sPid = trim(System::execString('pgrep -x ' . escapeshellarg($sDaemon) . ' 2>/dev/null || true'));

                if ($sPid !== '') {
                    return 'active';
                }

            } catch (CommandFailedException) {
            }
        }

        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * Reads the crontab of every user who has one
     *
     * Users without a crontab have no spool file, so are omitted rather than
     * reported as empty.
     *
     * @return array
     */
    private function getUserJobs(): array
    {
        if (!is_readable(self::SPOOL)) {
            return [
                'error' => 'Access to ' . self::SPOOL . ' denied - insufficient permissions (root required)',
            ];
        }

        $aFiles = scandir(self::SPOOL);
        if ($aFiles === false) {
            return [
                'error' => 'Failed to list ' . self::SPOOL,
            ];
        }

        $aUsers = [];
        foreach ($aFiles as $sFile) {

            $sPath = self::SPOOL . '/' . $sFile;
            if ($sFile === '.' || $sFile === '..' || !is_file($sPath)) {
                continue;
            }

            $sContents = @file_get_contents($sPath);
            if ($sContents === false) {
                continue;
            }

            //  The spool file is named for the user who owns the crontab
            $aUsers[$sFile] = $this->parseCrontab($sContents);
        }

        return $aUsers;
    }

    // --------------------------------------------------------------------------

    /**
     * Reads the system crontab and any drop-in files, both of which carry a
     * user column in addition to the schedule
     *
     * @return array
     */
    private function getSystemJobs(): array
    {
        $aPaths = [];

        if (is_file(self::SYSTEM_CRONTAB)) {
            $aPaths[] = self::SYSTEM_CRONTAB;
        }

        if (is_readable(self::CRON_D)) {
            $aFiles = scandir(self::CRON_D) ?: [];
            foreach ($aFiles as $sFile) {

                $sPath = self::CRON_D . '/' . $sFile;

                //  run-parts ignores files with a dot in the name, and so does cron
                if ($sFile === '.' || $sFile === '..' || str_contains($sFile, '.') || !is_file($sPath)) {
                    continue;
                }

                $aPaths[] = $sPath;
            }
        }

        $aSources = [];
        foreach ($aPaths as $sPath) {

            $sContents = @file_get_contents($sPath);
            if ($sContents === false) {
                $aSources[$sPath] = ['error' => 'Access to ' . $sPath . ' denied'];
                continue;
            }

            $aSources[$sPath] = $this->parseCrontab($sContents, true);
        }

        return $aSources;
    }

    // --------------------------------------------------------------------------

    /**
     * Lists the scripts installed in each run-parts directory
     *
     * @return array
     */
    private function getRunParts(): array
    {
        $aRunParts = [];

        foreach (self::RUN_PARTS as $sInterval => $sPath) {

            if (!is_readable($sPath)) {
                $aRunParts[$sInterval] = null;
                continue;
            }

            $aFiles = scandir($sPath) ?: [];
            $aRunParts[$sInterval] = array_values(
                array_filter(
                    $aFiles,
                    function ($sFile) use ($sPath) {
                        return $sFile !== '.'
                            && $sFile !== '..'
                            && !str_contains($sFile, '.')
                            && is_file($sPath . '/' . $sFile);
                    }
                )
            );
        }

        return $aRunParts;
    }

    // --------------------------------------------------------------------------

    /**
     * Parses the contents of a crontab into its environment and its jobs
     *
     * @param string $sContents     The crontab to parse
     * @param bool   $bHasUserField Whether lines carry a user column (as /etc/crontab does)
     *
     * @return array
     */
    public function parseCrontab(string $sContents, bool $bHasUserField = false): array
    {
        $aEnv  = [];
        $aJobs = [];

        foreach (preg_split('/\r?\n/', $sContents) ?: [] as $sLine) {

            $sLine = trim($sLine);

            if ($sLine === '' || str_starts_with($sLine, '#')) {
                continue;
            }

            //  Environment assignments, e.g. MAILTO=ops@example.com
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $sLine, $aMatches)) {
                $aEnv[$aMatches[1]] = $this->redactSecrets(trim($aMatches[2], '"\''));
                continue;
            }

            //  Shorthand schedules, e.g. @reboot
            if (preg_match('/^(' . self::SPECIAL . ')\s+(.*)$/', $sLine, $aMatches)) {
                $aJobs[] = $this->buildJob($aMatches[1], $aMatches[2], $bHasUserField);
                continue;
            }

            //  Five schedule fields, then the user (optionally) and the command
            $iLimit = $bHasUserField ? 7 : 6;
            $aParts = preg_split('/\s+/', $sLine, $iLimit) ?: [];

            if (count($aParts) < $iLimit) {
                continue;
            }

            $aJobs[] = $this->buildJob(
                implode(' ', array_slice($aParts, 0, 5)),
                implode(' ', array_slice($aParts, 5)),
                $bHasUserField
            );
        }

        return [
            'env'  => $aEnv,
            'jobs' => $aJobs,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Builds a single job, splitting the user off the command where present
     *
     * @param string $sSchedule     The schedule the job runs on
     * @param string $sRemainder    Everything following the schedule
     * @param bool   $bHasUserField Whether the remainder begins with a user
     *
     * @return array
     */
    private function buildJob(string $sSchedule, string $sRemainder, bool $bHasUserField): array
    {
        $sUser = null;

        if ($bHasUserField) {
            $aParts = preg_split('/\s+/', trim($sRemainder), 2) ?: [];
            $sUser  = $aParts[0] ?? null;
            //  A user with no command following it isn't a runnable job
            $sRemainder = $aParts[1] ?? '';
        }

        return [
            'schedule' => $sSchedule,
            'user'     => $sUser,
            'command'  => $this->redactSecrets(trim($sRemainder)),
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Masks credentials which have been written inline into a cron command.
     *
     * The heartbeat is sent off the server, so anything resembling a secret is
     * replaced whilst leaving the surrounding flag intact, keeping the shape of
     * the job legible.
     *
     * @param string $sValue The value to redact
     *
     * @return string
     */
    private function redactSecrets(string $sValue): string
    {
        foreach (self::SECRETS as $sPattern) {
            $sValue = preg_replace($sPattern, '$1[REDACTED]', $sValue) ?? $sValue;
        }

        if (preg_match(self::MYSQL_CLIENTS, $sValue)) {
            $sValue = preg_replace(self::SECRET_MYSQL_PASSWORD, '$1[REDACTED]', $sValue) ?? $sValue;
        }

        return $sValue;
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
