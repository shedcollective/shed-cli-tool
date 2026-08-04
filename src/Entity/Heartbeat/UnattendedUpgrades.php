<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Helper\System;

/**
 * Class UnattendedUpgrades
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class UnattendedUpgrades implements \JsonSerializable
{
    /**
     * The package which performs the upgrades
     *
     * @var string
     */
    private const PACKAGE = 'unattended-upgrades';

    /**
     * The timer which runs the upgrade itself
     *
     * @var string
     */
    private const TIMER_UPGRADE = 'apt-daily-upgrade.timer';

    /**
     * The timer which refreshes the package lists and pre-downloads packages
     *
     * @var string
     */
    private const TIMER_REFRESH = 'apt-daily.timer';

    /**
     * The apt config subtree governing whether, and how often, apt runs
     * unattended. Written by /etc/apt/apt.conf.d/20auto-upgrades.
     *
     * @var string
     */
    private const PREFIX_PERIODIC = 'APT::Periodic';

    /**
     * The apt config subtree governing what unattended-upgrades is permitted to
     * do once it runs. Written by /etc/apt/apt.conf.d/50unattended-upgrades.
     *
     * @var string
     */
    private const PREFIX_BEHAVIOUR = 'Unattended-Upgrade';

    /**
     * Where apt records the last successful run of each periodic phase. The
     * files are empty; it is their mtime which carries the information.
     *
     * @var string
     */
    private const STAMP_DIR = '/var/lib/apt/periodic';

    /**
     * The stamp files, keyed by the phase they record
     *
     * @var array
     */
    private const STAMPS = [
        'update'               => 'update-stamp',
        'update_success'       => 'update-success-stamp',
        'download_upgradeable' => 'download-upgradeable-stamp',
        'unattended_upgrade'   => 'unattended-upgrades-stamp',
        'autoclean'            => 'autoclean-stamp',
    ];

    /**
     * The upgrade log. Its directory is 0750 root:root on a stock install, so
     * this is only legible when the heartbeat runs as root.
     *
     * @var string
     */
    private const LOG = '/var/log/unattended-upgrades/unattended-upgrades.log';

    /**
     * How many lines of the log to report. The file is not rotated aggressively
     * and a full run writes several lines, so this covers the recent history
     * without bloating the payload.
     *
     * @var int
     */
    private const LOG_LINES = 25;

    /**
     * Set by apt when an upgrade cannot take effect until the host restarts.
     * Relevant whenever Automatic-Reboot is false, as patches then land but
     * remain dormant.
     *
     * @var string
     */
    private const REBOOT_REQUIRED = '/var/run/reboot-required';

    /**
     * The packages responsible for the pending restart
     *
     * @var string
     */
    private const REBOOT_REQUIRED_PKGS = '/var/run/reboot-required.pkgs';

    /**
     * The unit properties needed to describe a timer's window. Deliberately
     * narrow: `systemctl show` with no filter emits well over a hundred lines.
     *
     * @var array
     */
    private const TIMER_PROPERTIES = [
        'LoadState',
        'UnitFileState',
        'ActiveState',
        'TimersCalendar',
        'RandomizedDelayUSec',
        'NextElapseUSecRealtime',
        'LastTriggerUSec',
    ];

    /**
     * The apt config keys which decide whether anything happens at all, and the
     * value apt assumes when the key is absent entirely.
     *
     * `Enable` is normally unset and defaults to on. `Unattended-Upgrade` is the
     * opposite: absent means zero, which means never — it is 20auto-upgrades
     * which turns it on. Reading an absent key as "default" rather than "off"
     * would report an unconfigured host as healthy.
     *
     * @var array
     */
    private const PERIODIC_DEFAULTS = [
        'Enable'             => '1',
        'Unattended-Upgrade' => '0',
    ];

    /**
     * The unit file states which mean a timer will never fire
     *
     * @var array
     */
    private const TIMER_STATES_INERT = ['disabled', 'masked', 'masked-runtime'];

    /**
     * The apt config keys which are lists rather than scalars.
     *
     * apt emits an empty scalar assignment for a list which has no members, so
     * without this an unpopulated Package-Blacklist would arrive as "" whilst a
     * populated one arrives as an array, leaving consumers to handle both types
     * for the same key. Any key which takes appends is treated as a list too, so
     * this only needs to name those which might legitimately be empty.
     *
     * @var array
     */
    private const LIST_KEYS = [
        'Allowed-Origins',
        'Origins-Pattern',
        'Package-Blacklist',
        'Package-Whitelist',
    ];

    /**
     * systemd's time span units, expressed in microseconds.
     *
     * Some `*USec` properties are emitted as a bare microsecond count and others
     * as a human readable span ("12h", "1min 30s"), so both forms have to be
     * accepted. Note that `m` is minutes here, not months.
     *
     * @var array
     */
    private const DURATION_UNITS = [
        'usec'    => 1,
        'us'      => 1,
        'msec'    => 1000,
        'ms'      => 1000,
        'seconds' => 1000000,
        'second'  => 1000000,
        'sec'     => 1000000,
        's'       => 1000000,
        'minutes' => 60000000,
        'minute'  => 60000000,
        'min'     => 60000000,
        'm'       => 60000000,
        'hours'   => 3600000000,
        'hour'    => 3600000000,
        'hr'      => 3600000000,
        'h'       => 3600000000,
        'days'    => 86400000000,
        'day'     => 86400000000,
        'd'       => 86400000000,
        'weeks'   => 604800000000,
        'week'    => 604800000000,
        'w'       => 604800000000,
    ];

    // --------------------------------------------------------------------------

    /**
     * Gathers details about the host's unattended upgrade configuration
     *
     * Every nested structure here carries a fixed set of keys, reporting null or
     * an empty list for anything it could not establish, so that consumers can
     * rely on one shape per host regardless of distribution, init system or the
     * privileges the heartbeat ran with. Where a value is missing for a reason
     * worth knowing, an `error` key alongside it says so.
     *
     * The two config subtrees keep apt's own key names — `Allowed-Origins`
     * rather than `allowed_origins` — so that what is reported can be grepped
     * for directly in /etc/apt/apt.conf.d. Everything derived here uses the
     * house convention instead.
     *
     * @return array|null
     */
    public function get(): ?array
    {
        switch (Os::getType()) {
            case Os::LINUX:
                $aPeriodic  = $this->getConfig(self::PREFIX_PERIODIC);
                $aBehaviour = $this->getConfig(self::PREFIX_BEHAVIOUR);
                $aTimers    = [
                    'upgrade' => $this->getTimer(self::TIMER_UPGRADE),
                    'refresh' => $this->getTimer(self::TIMER_REFRESH),
                ];
                $bInstalled = $this->isInstalled();

                return [
                    'installed' => $bInstalled,
                    'version'   => $this->getVersion(),
                    'enabled'   => $this->isEnabled($bInstalled, $aPeriodic, $aTimers['upgrade']),
                    'blockers'  => $this->getBlockers($bInstalled, $aPeriodic, $aTimers['upgrade']),
                    'periodic'  => $this->serialiseConfig($aPeriodic),
                    'behaviour' => $this->serialiseConfig($aBehaviour),
                    'timers'    => $aTimers,
                    'last_run'  => $this->getStamps(),
                    'log'       => $this->getLog(),
                    'reboot'    => $this->getReboot(),
                ];

            case Os::MACOS:
                return null; // apt is Linux-only, as is the concept

            default:
                throw new HeartbeatException('Unable to determine unattended upgrade status.');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the unattended-upgrades package is installed
     *
     * @return bool
     */
    private function isInstalled(): bool
    {
        return $this->getPackageStatus() !== null;
    }

    // --------------------------------------------------------------------------

    /**
     * Reports the installed version of the package, if any
     *
     * @return string|null
     */
    private function getVersion(): ?string
    {
        $sStatus = $this->getPackageStatus();
        if ($sStatus === null) {
            return null;
        }

        //  "install ok installed 2.8ubuntu1" - the version is the final field
        $aParts   = preg_split('/\s+/', trim($sStatus)) ?: [];
        $sVersion = (string) array_pop($aParts);

        return $sVersion !== '' ? $sVersion : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Queries dpkg for the package's status line
     *
     * dpkg-query exits non-zero for an unknown package, and reports a status of
     * "deinstall ok config-files" for one which has been removed but not purged,
     * so both the exit code and the status itself have to be checked.
     *
     * @return string|null
     */
    private function getPackageStatus(): ?string
    {
        if (!System::commandExists('dpkg-query')) {
            return null;
        }

        try {

            $sStatus = System::execString(
                'dpkg-query -W -f=\'${Status} ${Version}\' ' . escapeshellarg(self::PACKAGE) . ' 2>/dev/null'
            );

        } catch (CommandFailedException) {
            return null;
        }

        return str_contains($sStatus, 'install ok installed') ? $sStatus : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Reads an apt config subtree
     *
     * `apt-config dump` is used in preference to reading the files under
     * /etc/apt/apt.conf.d directly, as it resolves the whole drop-in stack —
     * including vendor overrides and later files winning over earlier ones —
     * into the values apt will actually act on.
     *
     * The values are kept separate from any error rather than sharing a key
     * space with them, both so the shape is fixed and so that a config key of
     * apt's own named "error" could never be mistaken for one.
     *
     * @param string $sPrefix The subtree to read
     *
     * @return array{values: array, error: string|null}
     */
    private function getConfig(string $sPrefix): array
    {
        if (!System::commandExists('apt-config')) {
            return [
                'values' => [],
                'error'  => 'apt-config is not available on this host',
            ];
        }

        $aOutput = [];

        try {

            System::exec('apt-config dump ' . escapeshellarg($sPrefix) . ' 2>/dev/null', $aOutput);

        } catch (CommandFailedException $e) {
            return [
                'values' => [],
                'error'  => 'Failed to read ' . $sPrefix . ': ' . $e->getMessage(),
            ];
        }

        return [
            'values' => $this->parseAptConfig(implode("\n", $aOutput), $sPrefix),
            'error'  => null,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Prepares a config subtree for output
     *
     * The values are a keyed map, so are cast to an object to keep them
     * serialising as `{}` rather than `[]` when the subtree is empty — an empty
     * PHP array is indistinguishable from an empty list, which would change the
     * type the receiving API sees.
     *
     * @param array $aConfig The subtree, as returned by getConfig()
     *
     * @return array
     */
    private function serialiseConfig(array $aConfig): array
    {
        return [
            'values' => (object) $aConfig['values'],
            'error'  => $aConfig['error'],
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Parses the output of `apt-config dump`
     *
     * Lines take the form `Key "value";`. A key suffixed with `::` is a list
     * append rather than an assignment, which is how multi-valued settings such
     * as Allowed-Origins are expressed:
     *
     *     Unattended-Upgrade::Allowed-Origins "";
     *     Unattended-Upgrade::Allowed-Origins:: "${distro_id}:${distro_codename}-security";
     *
     * The bare assignment preceding the appends is a placeholder and is dropped
     * in favour of the list. Where a list has no members at all only that
     * placeholder is emitted, so the keys named in LIST_KEYS are forced to an
     * array regardless — otherwise the same key would arrive as a string on one
     * host and an array on another.
     *
     * @param string $sOutput The output to parse
     * @param string $sPrefix The subtree prefix to strip from each key
     *
     * @return array
     */
    public function parseAptConfig(string $sOutput, string $sPrefix): array
    {
        $aScalars = [];
        $aLists   = [];

        foreach (preg_split('/\r?\n/', $sOutput) ?: [] as $sLine) {

            if (!preg_match('/^(\S+)\s+"(.*)";$/', trim($sLine), $aMatches)) {
                continue;
            }

            $sKey   = $aMatches[1];
            $sValue = $aMatches[2];

            $bIsListItem = str_ends_with($sKey, '::');
            if ($bIsListItem) {
                $sKey = substr($sKey, 0, -2);
            }

            //  `dump` emits the root of the subtree as an empty assignment of its
            //  own before the keys beneath it; it carries nothing worth reporting
            if ($sKey === $sPrefix) {
                continue;
            }

            //  Report keys relative to the subtree that was asked for
            if (str_starts_with($sKey, $sPrefix . '::')) {
                $sKey = substr($sKey, strlen($sPrefix) + 2);
            }

            if ($sKey === '') {
                continue;
            }

            if ($bIsListItem) {
                $aLists[$sKey][] = $sValue;
            } else {
                $aScalars[$sKey] = $sValue;
            }
        }

        //  Promote the known list keys, preserving any value the placeholder
        //  carried rather than assuming it was empty
        foreach (self::LIST_KEYS as $sListKey) {
            if (array_key_exists($sListKey, $aScalars) && !array_key_exists($sListKey, $aLists)) {
                $aLists[$sListKey] = $aScalars[$sListKey] !== '' ? [$aScalars[$sListKey]] : [];
            }
        }

        //  A key which is a list is only a list; drop the placeholder behind it
        foreach (array_keys($aLists) as $sListKey) {
            unset($aScalars[$sListKey]);
        }

        return array_merge($aScalars, $aLists);
    }

    // --------------------------------------------------------------------------

    /**
     * Describes a systemd timer, and with it the window the upgrade runs in
     *
     * @param string $sUnit The timer to describe
     *
     * @return array
     */
    private function getTimer(string $sUnit): array
    {
        $aProperties = $this->showUnit($sUnit);

        if ($aProperties === null) {
            return $this->timerShape($sUnit, false, 'systemd is not available on this host');
        }

        //  A host still driving apt from /etc/cron.daily/apt-compat has no timer.
        //  That is a legitimate configuration rather than a fault, so no error is
        //  reported alongside it
        if (($aProperties['LoadState'] ?? '') === 'not-found') {
            return $this->timerShape($sUnit, false, null);
        }

        $aSchedules = $this->parseCalendar($aProperties['TimersCalendar'] ?? '');
        $iDelay     = $this->parseDuration($aProperties['RandomizedDelayUSec'] ?? '');
        $iDelaySecs = $iDelay !== null ? (int) ($iDelay / 1000000) : null;

        //  Merged over the base shape so the key set and their order hold whether
        //  the timer could be read or not
        return array_merge(
            $this->timerShape($sUnit, true, null),
            [
                'unit_file_state'  => $this->emptyToNull($aProperties['UnitFileState'] ?? ''),
                'active_state'     => $this->emptyToNull($aProperties['ActiveState'] ?? ''),
                'schedule'         => $aSchedules,
                'randomised_delay' => $iDelaySecs,
                'window'           => $this->describeWindow($aSchedules, $iDelaySecs),
                'next_run'         => $this->parseTimestamp($aProperties['NextElapseUSecRealtime'] ?? ''),
                'last_run'         => $this->parseTimestamp($aProperties['LastTriggerUSec'] ?? ''),
            ]
        );
    }

    // --------------------------------------------------------------------------

    /**
     * The shape every timer is reported in, holding nothing
     *
     * @param string      $sUnit    The timer being described
     * @param bool        $bPresent Whether the unit exists on the host
     * @param string|null $sError   Why it could not be read, where applicable
     *
     * @return array
     */
    private function timerShape(string $sUnit, bool $bPresent, ?string $sError): array
    {
        return [
            'unit'             => $sUnit,
            'present'          => $bPresent,
            'unit_file_state'  => null,
            'active_state'     => null,
            'schedule'         => [],
            'randomised_delay' => null,
            'window'           => null,
            'next_run'         => null,
            'last_run'         => null,
            'error'            => $sError,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Reads the properties of a systemd unit
     *
     * @param string $sUnit The unit to inspect
     *
     * @return array|null Null where systemd is unavailable
     */
    private function showUnit(string $sUnit): ?array
    {
        if (!System::commandExists('systemctl')) {
            return null;
        }

        $aOutput = [];

        try {

            System::exec(
                sprintf(
                    'systemctl show %s --no-pager --property=%s 2>/dev/null',
                    escapeshellarg($sUnit),
                    escapeshellarg(implode(',', self::TIMER_PROPERTIES))
                ),
                $aOutput
            );

        } catch (CommandFailedException) {
            //  Thrown on a host with systemctl present but no running systemd,
            //  such as inside a container
            return null;
        }

        $aProperties = [];
        foreach ($aOutput as $sLine) {

            //  Values may themselves contain "=", so only the first is a separator
            $aParts = explode('=', $sLine, 2);
            if (count($aParts) === 2) {
                $aProperties[$aParts[0]] = $aParts[1];
            }
        }

        return $aProperties;
    }

    // --------------------------------------------------------------------------

    /**
     * Extracts the calendar specs from systemd's TimersCalendar property
     *
     * The property is emitted as a brace-wrapped list of entries, each carrying
     * the spec alongside systemd's own resolution of it:
     *
     *     { OnCalendar=*-*-* 06:00:00 ; next_elapse=Wed 2026-08-05 06:00:00 UTC }
     *
     * Only the spec is taken; the next elapse is read from
     * NextElapseUSecRealtime instead, which is unambiguous.
     *
     * @param string $sValue The property value to parse
     *
     * @return array
     */
    public function parseCalendar(string $sValue): array
    {
        if (!preg_match_all('/OnCalendar=(.*?)\s*(?:;|})/', $sValue, $aMatches)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $aMatches[1])));
    }

    // --------------------------------------------------------------------------

    /**
     * Parses a systemd time span into microseconds
     *
     * Accepts both a bare microsecond count and a human readable span, as the
     * two are used interchangeably across properties and versions.
     *
     * @param string $sValue The value to parse
     *
     * @return int|null
     */
    public function parseDuration(string $sValue): ?int
    {
        $sValue = strtolower(trim($sValue));

        if ($sValue === '' || $sValue === 'infinity') {
            return null;
        }

        //  A bare number is already a microsecond count
        if (ctype_digit($sValue)) {
            return (int) $sValue;
        }

        if (!preg_match_all('/(\d+(?:\.\d+)?)\s*([a-z]+)/', $sValue, $aMatches, PREG_SET_ORDER)) {
            return null;
        }

        $iTotal = 0;
        foreach ($aMatches as $aMatch) {

            $sUnit = $aMatch[2];
            if (!array_key_exists($sUnit, self::DURATION_UNITS)) {
                return null;
            }

            $iTotal += (int) round(((float) $aMatch[1]) * self::DURATION_UNITS[$sUnit]);
        }

        return $iTotal;
    }

    // --------------------------------------------------------------------------

    /**
     * Parses a systemd timestamp property into an ISO 8601 string
     *
     * These are microseconds since the epoch. Zero means "never", which systemd
     * also expresses as "infinity" or an empty value depending on the property
     * and the version. Formatted values are tolerated for the same reason as in
     * parseDuration().
     *
     * @param string $sValue The value to parse
     *
     * @return string|null
     */
    public function parseTimestamp(string $sValue): ?string
    {
        $sValue = trim($sValue);

        if ($sValue === '' || $sValue === '0' || strtolower($sValue) === 'infinity') {
            return null;
        }

        if (ctype_digit($sValue)) {
            return $this->toIso8601((int) ((int) $sValue / 1000000));
        }

        $iTimestamp = strtotime($sValue);

        return $iTimestamp !== false ? $this->toIso8601($iTimestamp) : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Summarises when a timer fires, in a form which can be shown as-is
     *
     * The randomised delay is what makes this a window rather than a time: every
     * host in the fleet is given the same calendar spec and spreads itself out
     * across the delay, so the base time alone is misleading.
     *
     * @param array    $aSchedules   The calendar specs the timer runs on
     * @param int|null $iDelaySeconds The randomised delay applied to each
     *
     * @return string|null
     */
    private function describeWindow(array $aSchedules, ?int $iDelaySeconds): ?string
    {
        if (empty($aSchedules)) {
            return null;
        }

        $sWindow = implode(', ', $aSchedules);

        if ($iDelaySeconds !== null && $iDelaySeconds > 0) {
            $sWindow .= ' + up to ' . $this->humaniseSeconds($iDelaySeconds);
        }

        return $sWindow;
    }

    // --------------------------------------------------------------------------

    /**
     * Renders a number of seconds as a short human readable duration
     *
     * @param int $iSeconds The duration to render
     *
     * @return string
     */
    private function humaniseSeconds(int $iSeconds): string
    {
        $aParts   = [];
        $iHours   = intdiv($iSeconds, 3600);
        $iMinutes = intdiv($iSeconds % 3600, 60);
        $iRemains = $iSeconds % 60;

        if ($iHours) {
            $aParts[] = $iHours . 'h';
        }

        if ($iMinutes) {
            $aParts[] = $iMinutes . 'm';
        }

        if ($iRemains || empty($aParts)) {
            $aParts[] = $iRemains . 's';
        }

        return implode(' ', $aParts);
    }

    // --------------------------------------------------------------------------

    /**
     * Reports when each periodic phase last succeeded
     *
     * The stamp directory is world readable, which makes this the most reliable
     * evidence available without root that upgrades are not merely configured
     * but actually running.
     *
     * @return array
     */
    private function getStamps(): array
    {
        $aStamps = [];

        foreach (self::STAMPS as $sPhase => $sFile) {

            $sPath = self::STAMP_DIR . '/' . $sFile;

            if (!is_file($sPath)) {
                $aStamps[$sPhase] = null;
                continue;
            }

            $iModified        = @filemtime($sPath);
            $aStamps[$sPhase] = $iModified !== false ? $this->toIso8601($iModified) : null;
        }

        return $aStamps;
    }

    // --------------------------------------------------------------------------

    /**
     * Reports the tail of the upgrade log
     *
     * @return array
     */
    private function getLog(): array
    {
        if (!is_file(self::LOG)) {
            return $this->logShape(
                false,
                self::LOG . ' does not exist - unattended-upgrades may never have run'
            );
        }

        if (!is_readable(self::LOG)) {
            return $this->logShape(
                false,
                'Access to ' . self::LOG . ' denied - insufficient permissions (root required)'
            );
        }

        $aOutput = [];

        try {

            System::exec('tail -n ' . self::LOG_LINES . ' ' . escapeshellarg(self::LOG), $aOutput);

        } catch (CommandFailedException $e) {
            return $this->logShape(true, 'Failed to read ' . self::LOG . ': ' . $e->getMessage());
        }

        $iModified = @filemtime(self::LOG);

        return array_merge(
            $this->logShape(true, null),
            [
                'last_modified' => $iModified !== false ? $this->toIso8601($iModified) : null,
                'tail'          => $aOutput,
            ]
        );
    }

    // --------------------------------------------------------------------------

    /**
     * The shape the log is reported in, holding nothing
     *
     * @param bool        $bReadable Whether the log could be read
     * @param string|null $sError    Why it could not be, where applicable
     *
     * @return array
     */
    private function logShape(bool $bReadable, ?string $sError): array
    {
        return [
            'path'          => self::LOG,
            'readable'      => $bReadable,
            'last_modified' => null,
            'tail'          => [],
            'error'         => $sError,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Reports whether an upgrade is waiting on a restart to take effect
     *
     * @return array
     */
    private function getReboot(): array
    {
        $bRequired = file_exists(self::REBOOT_REQUIRED);
        $aPackages = [];

        if ($bRequired && is_readable(self::REBOOT_REQUIRED_PKGS)) {
            $aContents = @file(self::REBOOT_REQUIRED_PKGS, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $aPackages = array_values(array_unique($aContents ?: []));
        }

        return [
            'required' => $bRequired,
            'packages' => $aPackages,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Decides whether the host will actually apply upgrades unattended
     *
     * A single boolean is offered alongside the raw configuration because the
     * three things which independently switch this off live in three different
     * places, and the common misconfiguration — package installed, timer active,
     * interval left at zero — looks healthy from any one of them alone.
     *
     * @param bool  $bInstalled Whether the package is installed
     * @param array $aPeriodic  The APT::Periodic subtree, as returned by getConfig()
     * @param array $aTimer     The upgrade timer
     *
     * @return bool
     */
    private function isEnabled(bool $bInstalled, array $aPeriodic, array $aTimer): bool
    {
        return empty($this->getBlockers($bInstalled, $aPeriodic, $aTimer));
    }

    // --------------------------------------------------------------------------

    /**
     * Lists the reasons upgrades will not be applied unattended
     *
     * @param bool  $bInstalled Whether the package is installed
     * @param array $aPeriodic  The APT::Periodic subtree, as returned by getConfig()
     * @param array $aTimer     The upgrade timer
     *
     * @return array
     */
    private function getBlockers(bool $bInstalled, array $aPeriodic, array $aTimer): array
    {
        $aBlockers = [];

        if (!$bInstalled) {
            $aBlockers[] = 'The ' . self::PACKAGE . ' package is not installed';
        }

        if ($aPeriodic['error'] !== null) {

            //  Without the config there is no basis for claiming a specific key is
            //  off, so the unread config is itself reported as the impediment
            $aBlockers[] = 'The ' . self::PREFIX_PERIODIC . ' configuration could not be read: '
                . $aPeriodic['error'];

        } else {

            $aValues = $aPeriodic['values'];

            //  Where a key is absent apt falls back to its own default, which
            //  differs per key; see PERIODIC_DEFAULTS
            $sEnable   = (string) ($aValues['Enable'] ?? self::PERIODIC_DEFAULTS['Enable']);
            $sInterval = (string) ($aValues['Unattended-Upgrade'] ?? self::PERIODIC_DEFAULTS['Unattended-Upgrade']);

            if ($sEnable === '0') {
                $aBlockers[] = 'APT::Periodic::Enable is 0, which disables all periodic apt activity';
            }

            if ($sInterval === '0') {
                $aBlockers[] = 'APT::Periodic::Unattended-Upgrade is 0, so upgrades are never applied';
            }
        }

        $sUnitState = (string) ($aTimer['unit_file_state'] ?? '');
        if (in_array($sUnitState, self::TIMER_STATES_INERT, true)) {
            $aBlockers[] = self::TIMER_UPGRADE . ' is ' . $sUnitState . ', so it will never fire';
        }

        return $aBlockers;
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a unix timestamp as ISO 8601, in UTC
     *
     * @param int $iTimestamp The timestamp to format
     *
     * @return string
     */
    private function toIso8601(int $iTimestamp): string
    {
        return (new \DateTimeImmutable('@' . $iTimestamp))->format(DATE_ATOM);
    }

    // --------------------------------------------------------------------------

    /**
     * Normalises an absent systemd property to null rather than an empty string
     *
     * @param string $sValue The value to normalise
     *
     * @return string|null
     */
    private function emptyToNull(string $sValue): ?string
    {
        $sValue = trim($sValue);
        return $sValue !== '' ? $sValue : null;
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
