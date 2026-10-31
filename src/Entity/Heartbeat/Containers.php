<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Helper\Containers as ContainerHelper;

/**
 * Class Containers
 *
 * Inventories each running container's runtimes — its base OS, and the PHP, Node, MySQL, MariaDB,
 * Postgres, Redis and web server versions inside it. On a Docker host those are what the
 * application actually runs, and the host's own `php` / `mysql` / `node` blocks are not.
 *
 * ## What it runs, and what it never reads
 *
 * One `docker exec` per container runs a short POSIX script that asks each runtime it can find for
 * its `--version` (or `-v`), and reads `/etc/os-release`. **Version commands only**: never the
 * environment, never a config file, never application data. A container with no shell (distroless)
 * gets the same binaries asked directly, one exec each, and its os-release read through `docker cp`.
 * See {@see ContainerHelper} for why everything goes through the Docker API rather than `/proc`.
 *
 * ## Shape
 *
 * - `null` on a host without Docker, and on macOS.
 * - `error` is set, with `items: []` and `count: null`, when Docker is there but cannot be asked
 *   (the socket is not accessible, the daemon is down). Unknown, never "no containers".
 * - Each item's `packages` has a fixed set of keys. `null` under one means the binary was not
 *   found in that container; a found binary whose version could not be parsed keeps its `raw`
 *   banner with `version: null`. `packages` itself is `null` when the container was not probed,
 *   and `errors` says why.
 * - Stopped containers are not reported.
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Containers implements \JsonSerializable
{
    /**
     * The packages reported for every container, in order. php_fpm and npm are reported beside the
     * runtime they ship with; mysql and mariadb are told apart by the banner, never by the filename.
     *
     * @var array<int, string>
     */
    public const PACKAGES = [
        'php',
        'php_fpm',
        'node',
        'npm',
        'mysql',
        'mariadb',
        'postgres',
        'redis',
        'caddy',
        'nginx',
        'apache',
    ];

    /**
     * Each probe: the package it answers for, and the commands to try in order (the first found
     * wins). `mysqld` and `mariadbd` both answer for whichever engine their banner names.
     *
     * @var array<string, array<int, array<int, string>>>
     */
    private const PROBES = [
        'php'      => [['php', '-v']],
        'php_fpm'  => [['php-fpm', '-v']],
        //  Distroless images keep node off the PATH, where nothing but their entrypoint finds it
        'node'     => [['node', '--version'], ['/nodejs/bin/node', '--version']],
        'npm'      => [['npm', '--version']],
        'mariadbd' => [['mariadbd', '--version']],
        'mysqld'   => [['mysqld', '--version']],
        'postgres' => [['postgres', '--version']],
        'redis'    => [['redis-server', '--version']],
        'caddy'    => [['caddy', 'version']],
        'nginx'    => [['nginx', '-v']],
        'apache'   => [['apache2', '-v'], ['httpd', '-v']],
    ];

    /**
     * Marks the start of each answer in the probe script's output. Unlikely enough in a version
     * banner that it cannot be confused for one.
     *
     * @var string
     */
    private const MARKER = '@@shed-probe:';

    /**
     * The longest raw banner kept
     *
     * @var int
     */
    private const LIMIT_RAW = 200;

    /**
     * How much of os-release is read
     *
     * @var int
     */
    private const LIMIT_OS_RELEASE = 4096;

    // --------------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    public function get(): ?array
    {
        switch (Os::getType()) {
            case Os::LINUX:
                if (!ContainerHelper::dockerInstalled()) {
                    return null;
                }

                return $this->collect();

            case Os::MACOS:
                return null;

            default:
                throw new HeartbeatException('Unable to determine container status.');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function collect(): array
    {
        $aState = ContainerHelper::state();

        if ($aState['client'] === null) {
            return [
                'runtime'   => ContainerHelper::RUNTIME_DOCKER,
                'error'     => $aState['error'] ?? 'Docker cannot be asked',
                'count'     => null,
                'truncated' => false,
                'items'     => [],
            ];
        }

        $aItems     = [];
        $bTruncated = $aState['truncated'];

        //  Ordered by name so that, when the budget runs out, the same containers are the ones
        //  probed from one beat to the next
        $aContainers = array_values($aState['containers']);
        usort($aContainers, fn(array $aA, array $aB): int => strcmp((string) $aA['name'], (string) $aB['name']));

        foreach ($aContainers as $aContainer) {

            $aItem = [
                'id'            => substr($aContainer['id'], 0, 12),
                'name'          => $aContainer['name'],
                'image'         => $aContainer['image'],
                'image_id'      => $aContainer['image_id'],
                'created'       => $aContainer['created'],
                'started_at'    => $aContainer['started_at'],
                'status'        => $aContainer['status'],
                'restart_count' => $aContainer['restart_count'],
                'compose'       => $aContainer['compose'],
                'os'            => null,
                'packages'      => null,
                'errors'        => [],
            ];

            if (!ContainerHelper::withinBudget(ContainerHelper::BUDGET_PROBES)) {
                $bTruncated      = true;
                $aItem['errors'] = ['Not probed: the container pass ran out of time'];
                $aItems[]        = $aItem;
                continue;
            }

            $aProbe = $this->probe($aContainer['id']);

            $aItem['os']       = $aProbe['os'];
            $aItem['packages'] = $aProbe['packages'];
            $aItem['errors']   = $aProbe['errors'];
            $aItems[]          = $aItem;
        }

        return [
            'runtime'   => ContainerHelper::RUNTIME_DOCKER,
            'error'     => $aState['error'],
            //  Every running container `docker ps` listed, including any past the inspection cap
            'count'     => $aState['count'],
            'truncated' => $bTruncated,
            'items'     => $aItems,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Asks one container what it runs
     *
     * @return array{os: array<string, string|null>|null, packages: array<string, mixed>|null, errors: array<int, string>}
     */
    private function probe(string $sId): array
    {
        $aResult = ContainerHelper::exec($sId, ['sh', '-c', self::script()], ContainerHelper::BUDGET_PROBES);

        if ($aResult['error'] !== null) {
            return ['os' => null, 'packages' => null, 'errors' => [$aResult['error']]];
        }

        $aAnswers = self::parseProbeOutput($aResult['output']);

        if ($aAnswers !== null) {
            return [
                'os'       => self::parseOsRelease($aAnswers['os'] ?? ''),
                'packages' => self::packages($aAnswers),
                'errors'   => [],
            ];
        }

        //  The script never ran. A container without a shell says so through the runtime; anything
        //  else is a failure worth reporting as one
        $sOutput = implode(' ', $aResult['output']);

        if (!preg_match('/executable file not found|no such file or directory/i', $sOutput)) {
            return ['os' => null, 'packages' => null, 'errors' => [self::firstLine($sOutput) ?? 'The container did not answer']];
        }

        return $this->probeWithoutShell($sId);
    }

    // --------------------------------------------------------------------------

    /**
     * Asks a container with no shell: each binary directly, resolved by the runtime against the
     * image's own PATH, and os-release read through the daemon
     *
     * @return array{os: array<string, string|null>|null, packages: array<string, mixed>|null, errors: array<int, string>}
     */
    private function probeWithoutShell(string $sId): array
    {
        $aAnswers = [];
        $aErrors  = ['No shell in the container; each runtime was asked directly'];

        foreach (self::PROBES as $sProbe => $aCommands) {
            foreach ($aCommands as $aCommand) {

                $aResult = ContainerHelper::exec($sId, $aCommand, ContainerHelper::BUDGET_PROBES);

                if ($aResult['error'] !== null) {
                    $aErrors[] = $sProbe . ': ' . $aResult['error'];
                    break 2;
                }

                $sOutput = implode("\n", $aResult['output']);

                if (preg_match('/executable file not found|no such file or directory/i', $sOutput)) {
                    continue;
                }

                $aAnswers[$sProbe] = $sOutput;
                break;
            }
        }

        $sOsRelease = ContainerHelper::readFile($sId, '/etc/os-release', self::LIMIT_OS_RELEASE, ContainerHelper::BUDGET_PROBES);

        return [
            'os'       => $sOsRelease !== null ? self::parseOsRelease($sOsRelease) : null,
            'packages' => self::packages($aAnswers),
            'errors'   => $aErrors,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * The script run inside each container. POSIX sh, and nothing busybox lacks, so it runs on
     * Alpine as well as Debian.
     *
     * Each found binary's answer follows a marker line naming the probe. `t` bounds a single command
     * where the image has `timeout`; the host-side `timeout` around `docker exec` bounds the whole.
     * `php-fpm` is versioned on Debian (`php-fpm8.4`), and Debian's postgres is not on the PATH, so
     * both are also looked for where the packages put them.
     */
    public static function script(): string
    {
        $sMarker = self::MARKER;
        $aLines  = [
            't() { if command -v timeout >/dev/null 2>&1; then timeout ' . ContainerHelper::EXEC_TIMEOUT . ' "$@"; else "$@"; fi; }',
            'p() { n=$1; shift; if command -v "$1" >/dev/null 2>&1; then echo "' . $sMarker . '$n"; t "$@" 2>&1; return 0; fi; return 1; }',
            'echo "' . $sMarker . 'os"; cat /etc/os-release 2>/dev/null || cat /usr/lib/os-release 2>/dev/null',
        ];

        foreach (self::PROBES as $sProbe => $aCommands) {
            $aTries = array_map(
                fn(array $aCommand): string => 'p ' . $sProbe . ' ' . implode(' ', array_map('escapeshellarg', $aCommand)),
                $aCommands
            );

            if ($sProbe === 'php_fpm') {
                $aTries[] = 'for b in /usr/sbin/php-fpm* /usr/local/sbin/php-fpm*; do [ -x "$b" ] && p php_fpm "$b" -v && break; done';
            }

            if ($sProbe === 'postgres') {
                $aTries[] = 'for b in /usr/lib/postgresql/*/bin/postgres; do [ -x "$b" ] && p postgres "$b" --version && break; done';
            }

            $aLines[] = implode(' || ', $aTries) . ' || true';
        }

        return implode("\n", $aLines) . "\n";
    }

    // --------------------------------------------------------------------------

    /**
     * Splits the probe script's output into each probe's answer
     *
     * @param array<int, string> $aOutput
     *
     * @return array<string, string>|null Null where the script never ran
     */
    public static function parseProbeOutput(array $aOutput): ?array
    {
        $aAnswers = [];
        $sCurrent = null;

        foreach ($aOutput as $sLine) {
            if (str_starts_with($sLine, self::MARKER)) {
                $sCurrent            = substr($sLine, strlen(self::MARKER));
                $aAnswers[$sCurrent] = $aAnswers[$sCurrent] ?? '';
                continue;
            }

            if ($sCurrent !== null) {
                $aAnswers[$sCurrent] .= $sLine . "\n";
            }
        }

        return array_key_exists('os', $aAnswers) ? $aAnswers : null;
    }

    // --------------------------------------------------------------------------

    /**
     * The fixed package map, from each probe's raw answer
     *
     * @param array<string, string> $aAnswers
     *
     * @return array<string, array{version: string|null, raw: string|null}|null>
     */
    public static function packages(array $aAnswers): array
    {
        $aPackages = array_fill_keys(self::PACKAGES, null);

        foreach (['php', 'php_fpm', 'node', 'npm', 'postgres', 'redis', 'caddy', 'nginx', 'apache'] as $sPackage) {
            if (array_key_exists($sPackage, $aAnswers)) {
                $aPackages[$sPackage] = self::parseVersion($sPackage, $aAnswers[$sPackage]);
            }
        }

        //  A MariaDB image ships `mysqld` as well as `mariadbd`, so both are asked and each answer
        //  is filed under the engine its banner names. Asked first, mariadbd wins a tie.
        foreach (['mariadbd', 'mysqld'] as $sProbe) {
            if (!array_key_exists($sProbe, $aAnswers)) {
                continue;
            }

            $sEngine = stripos($aAnswers[$sProbe], 'mariadb') !== false ? 'mariadb' : 'mysql';

            if ($aPackages[$sEngine] === null) {
                $aPackages[$sEngine] = self::parseVersion($sEngine, $aAnswers[$sProbe]);
            }
        }

        return $aPackages;
    }

    // --------------------------------------------------------------------------

    /**
     * Takes the version out of a runtime's banner
     *
     * @return array{version: string|null, raw: string|null}
     */
    public static function parseVersion(string $sPackage, string $sOutput): array
    {
        $sRaw = self::firstLine($sOutput, $sPackage);

        $sPattern = match ($sPackage) {
            'php', 'php_fpm' => '/\bPHP (\d+\.\d+\.\d+)/',
            'node'           => '/^v?(\d+\.\d+\.\d+)/',
            'npm'            => '/^(\d+\.\d+\.\d+)/',
            'mysql', 'mariadb' => '/\bVer (?:\d+\.\d+\.\d+ Distrib )?(\d+\.\d+\.\d+)/',
            'postgres'       => '/\(PostgreSQL\) (\d+(?:\.\d+){1,2})/',
            'redis'          => '/\bv=(\d+\.\d+\.\d+)/',
            'caddy'          => '/^v?(\d+\.\d+\.\d+)/',
            'nginx'          => '/nginx\/(\d+\.\d+\.\d+)/',
            'apache'         => '/Apache\/(\d+\.\d+\.\d+)/',
            default          => '/(\d+\.\d+\.\d+)/',
        };

        $sVersion = $sRaw !== null && preg_match($sPattern, $sRaw, $aMatch) ? $aMatch[1] : null;

        return ['version' => $sVersion, 'raw' => $sRaw];
    }

    // --------------------------------------------------------------------------

    /**
     * Only `ID`, `VERSION_ID` and `PRETTY_NAME` are kept from os-release
     *
     * @return array{id: string|null, version: string|null, pretty_name: string|null}|null
     */
    public static function parseOsRelease(string $sContents): ?array
    {
        $aValues = [];

        foreach (preg_split('/\r?\n/', $sContents) ?: [] as $sLine) {
            if (!preg_match('/^(ID|VERSION_ID|PRETTY_NAME)=(.*)$/', trim($sLine), $aMatch)) {
                continue;
            }

            $sValue = trim($aMatch[2]);
            if (strlen($sValue) >= 2 && ($sValue[0] === '"' || $sValue[0] === "'") && $sValue[-1] === $sValue[0]) {
                $sValue = substr($sValue, 1, -1);
            }

            $aValues[$aMatch[1]] = self::printable($sValue);
        }

        if ($aValues === []) {
            return null;
        }

        return [
            'id'          => ($aValues['ID'] ?? '') !== '' ? $aValues['ID'] : null,
            'version'     => ($aValues['VERSION_ID'] ?? '') !== '' ? $aValues['VERSION_ID'] : null,
            'pretty_name' => ($aValues['PRETTY_NAME'] ?? '') !== '' ? $aValues['PRETTY_NAME'] : null,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * The line of a banner that carries the version, capped and made safe to send
     *
     * Most runtimes put the version first. Apache prints `Server version:` first and the build date
     * second, nginx prints only to stderr, and a warning (apache2 complaining about its environment
     * variables, for instance) can come first — so the first line naming the runtime is preferred.
     */
    private static function firstLine(string $sOutput, ?string $sPackage = null): ?string
    {
        $aLines = array_values(array_filter(
            array_map('trim', preg_split('/\r?\n/', $sOutput) ?: []),
            fn(string $sLine): bool => $sLine !== ''
        ));

        if ($aLines === []) {
            return null;
        }

        $sLine = $aLines[0];

        $sNeedle = match ($sPackage) {
            'php', 'php_fpm'   => '/\bPHP \d/',
            'apache'           => '/Apache\/\d/',
            'nginx'            => '/nginx\/\d/',
            'postgres'         => '/PostgreSQL\) \d/',
            'redis'            => '/\bv=\d/',
            'mysql', 'mariadb' => '/ Ver \d/',
            default            => null,
        };

        if ($sNeedle !== null) {
            foreach ($aLines as $sCandidate) {
                if (preg_match($sNeedle, $sCandidate)) {
                    $sLine = $sCandidate;
                    break;
                }
            }
        }

        return self::printable($sLine);
    }

    // --------------------------------------------------------------------------

    /**
     * Capped, and reduced to printable ASCII — every banner and os-release field asked for is
     * ASCII, and this keeps a container's output from putting invalid UTF-8 into the payload
     */
    private static function printable(string $sValue): string
    {
        return substr(preg_replace('/[^\x20-\x7E]/', '?', $sValue) ?? '', 0, self::LIMIT_RAW);
    }

    // --------------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
}
