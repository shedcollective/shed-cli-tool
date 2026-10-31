<?php

namespace Shed\Cli\Helper;

/**
 * Class Containers
 *
 * What the heartbeat knows about the containers on a host, shared between the `node` block (which
 * attributes a running process to the container it is in) and the `containers` block (which
 * inventories each running container's runtimes).
 *
 * ## Read-only, and only through the Docker API
 *
 * A container's filesystem belongs to whoever built its image. The heartbeat runs as root, so
 * executing a container's binary from the host — directly through `/proc/<pid>/root`, or with
 * `nsenter`, which keeps the caller's capabilities and skips the container's seccomp and AppArmor
 * profiles — would run that image author's code as host root. Every command therefore goes through
 * `docker exec`, which runs it inside the container's own isolation, as the container's own user.
 * Nothing is written, and `docker exec` never starts a stopped container.
 *
 * ## Always on, and free without containers
 *
 * A process's container is read from its cgroup, so a host without containers pays nothing, and
 * Docker is only asked anything once a container process is found or, for the `containers` block,
 * on a host where Docker is installed.
 *
 * ## Bounded
 *
 * Every exec is under a `timeout`, and each of the two passes (attributing node processes, and
 * probing containers) has its own overall budget, so a host with dozens of containers cannot stall
 * the beat. What the budget cuts short is reported as such, never as absent.
 *
 * @package Shed\Cli\Helper
 */
final class Containers
{
    public const RUNTIME_DOCKER     = 'docker';
    public const RUNTIME_CONTAINERD = 'containerd';
    public const RUNTIME_PODMAN     = 'podman';

    /**
     * The two passes that share the Docker client, each with its own time budget
     *
     * @var string
     */
    public const BUDGET_PROCESSES = 'processes';
    public const BUDGET_PROBES    = 'probes';

    /**
     * Seconds each pass may spend in total
     *
     * @var array<string, int>
     */
    private const BUDGETS = [
        self::BUDGET_PROCESSES => 10,
        self::BUDGET_PROBES    => 20,
    ];

    /**
     * How long one command inside a container may take
     *
     * @var int
     */
    public const EXEC_TIMEOUT = 3;

    /**
     * How long a call to the daemon itself (list, inspect) may take
     *
     * @var int
     */
    private const DAEMON_TIMEOUT = 5;

    /**
     * The most output read back from one exec. Version banners are a line or two; this stops a
     * misbehaving container from filling the heartbeat's memory.
     *
     * @var int
     */
    private const OUTPUT_LIMIT = 65536;

    /**
     * The most running containers inspected
     *
     * @var int
     */
    public const LIMIT_CONTAINERS = 100;

    /**
     * Client binaries, in preference order
     *
     * @var array<int, string>
     */
    private const CLIENTS = [
        '/usr/bin/docker',
        '/usr/local/bin/docker',
    ];

    /**
     * Where the daemon listens by default
     *
     * @var string
     */
    private const SOCKET = '/var/run/docker.sock';

    /**
     * The fields read from `docker inspect`. Each is JSON-encoded by the template so that a tab or
     * newline in a value cannot shift the columns. **Deliberately not `{{json .}}`**: the full
     * document carries the container's environment, which is where its secrets live, and nothing
     * here should ever hold those, even in memory.
     *
     * @var array<string, string>
     */
    private const INSPECT_FIELDS = [
        'id'              => '{{json .Id}}',
        'name'            => '{{json .Name}}',
        'image'           => '{{json .Config.Image}}',
        'image_id'        => '{{json .Image}}',
        'created'         => '{{json .Created}}',
        'started_at'      => '{{json .State.StartedAt}}',
        'status'          => '{{json .State.Status}}',
        'restart_count'   => '{{json .RestartCount}}',
        'compose_project' => '{{json (index .Config.Labels "com.docker.compose.project")}}',
        'compose_service' => '{{json (index .Config.Labels "com.docker.compose.service")}}',
    ];

    /**
     * The client, its error, and the running containers keyed by full id, established once per run
     *
     * @var array{client: string|null, error: string|null, count: int|null, containers: array<string, array<string, mixed>>, truncated: bool}|null
     */
    private static ?array $aState = null;

    /**
     * When each budget runs out, as a monotonic timestamp in seconds
     *
     * @var array<string, float>
     */
    private static array $aDeadlines = [];

    // --------------------------------------------------------------------------

    /**
     * Forgets everything established this run. For tests.
     */
    public static function reset(): void
    {
        self::$aState     = null;
        self::$aDeadlines = [];
    }

    // --------------------------------------------------------------------------

    /**
     * Whether Docker looks to be on this host at all: a daemon binary, or its socket
     */
    public static function dockerInstalled(): bool
    {
        foreach (['/usr/bin/dockerd', '/usr/sbin/dockerd', '/usr/local/bin/dockerd'] as $sPath) {
            if (is_file($sPath)) {
                return true;
            }
        }

        return file_exists(self::SOCKET);
    }

    // --------------------------------------------------------------------------

    /**
     * The client, why it cannot be used if it cannot, and every running container
     *
     * `count` is every running container `docker ps` listed — `null` when it could not be asked,
     * never zero — and `containers` the ones inspected, capped at LIMIT_CONTAINERS.
     *
     * @return array{client: string|null, error: string|null, count: int|null, containers: array<string, array<string, mixed>>, truncated: bool}
     */
    public static function state(): array
    {
        if (self::$aState !== null) {
            return self::$aState;
        }

        $aState = ['client' => null, 'error' => null, 'count' => null, 'containers' => [], 'truncated' => false];

        $sClient = null;
        foreach (self::CLIENTS as $sPath) {
            if (is_file($sPath) && is_executable($sPath)) {
                $sClient = $sPath;
                break;
            }
        }

        if ($sClient === null) {
            $aState['error'] = 'The docker client is not installed';
            return self::$aState = $aState;
        }

        if (!Trust::isRootOwned($sClient)) {
            $aState['error'] = 'Not executed: ' . $sClient . ' is not owned exclusively by root';
            return self::$aState = $aState;
        }

        $aState['client'] = $sClient;

        $aIds = self::run(self::bounded($sClient, ['ps', '--quiet', '--no-trunc'], self::DAEMON_TIMEOUT));

        if ($aIds['exit'] !== 0) {
            $aState['client'] = null;
            $aState['error']  = self::daemonError($aIds['output']);
            return self::$aState = $aState;
        }

        $aIds = array_values(array_filter(
            array_map('trim', $aIds['output']),
            fn(string $sId): bool => (bool) preg_match('/^[0-9a-f]{64}$/', $sId)
        ));

        $aState['count'] = count($aIds);

        if (count($aIds) > self::LIMIT_CONTAINERS) {
            $aState['truncated'] = true;
            $aIds                = array_slice($aIds, 0, self::LIMIT_CONTAINERS);
        }

        if ($aIds !== []) {
            $aInspect = self::run(self::bounded(
                $sClient,
                array_merge(['inspect', '--format', implode("\t", self::INSPECT_FIELDS)], $aIds),
                self::DAEMON_TIMEOUT
            ));

            foreach ($aInspect['output'] as $sLine) {
                $aContainer = self::parseInspectLine($sLine);
                if ($aContainer !== null) {
                    $aState['containers'][$aContainer['id']] = $aContainer;
                }
            }

            if ($aState['containers'] === [] && $aInspect['exit'] !== 0) {
                $aState['error'] = 'Running containers were listed, but could not be inspected';
            }
        }

        return self::$aState = $aState;
    }

    // --------------------------------------------------------------------------

    /**
     * A container's identity, for attaching to whatever runs inside it
     *
     * The name and image are `null` where Docker cannot be asked; the id is still worth reporting,
     * since it ties the process to `docker ps` output on the host.
     *
     * @param array{id: string, runtime: string} $aContainer As returned by forProcess()
     *
     * @return array{id: string, name: string|null, image: string|null, runtime: string}
     */
    public static function describe(array $aContainer): array
    {
        $aKnown = $aContainer['runtime'] === self::RUNTIME_DOCKER
            ? (self::state()['containers'][$aContainer['id']] ?? null)
            : null;

        return [
            'id'      => substr($aContainer['id'], 0, 12),
            'name'    => $aKnown['name'] ?? null,
            'image'   => $aKnown['image'] ?? null,
            'runtime' => $aContainer['runtime'],
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Runs a command inside a running container
     *
     * @param string             $sId      The full container id
     * @param array<int, string> $aCommand The command and its arguments
     * @param string             $sBudget  Which pass this exec is charged to
     *
     * @return array{output: array<int, string>, error: string|null}
     */
    public static function exec(string $sId, array $aCommand, string $sBudget): array
    {
        $aState = self::state();

        if ($aState['client'] === null) {
            return ['output' => [], 'error' => $aState['error'] ?? 'Docker cannot be asked'];
        }

        if (!self::withinBudget($sBudget)) {
            return ['output' => [], 'error' => 'Not inspected: the container pass ran out of time'];
        }

        $aResult = self::run(self::bounded(
            $aState['client'],
            array_merge(['exec', $sId], $aCommand),
            self::EXEC_TIMEOUT
        ));

        return [
            'output' => $aResult['output'],
            'error'  => $aResult['exit'] === 124 ? 'Timed out after ' . self::EXEC_TIMEOUT . 's' : null,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Reads one file out of a running container, through the daemon rather than through
     * `/proc/<pid>/root` — an absolute symlink inside the container would otherwise resolve against
     * the host's root
     *
     * `docker cp` is read-only and needs nothing inside the container, so this works where there is
     * no shell to `cat` with. Symlinks are followed inside the container (`-L`).
     *
     * @param string $sId    The full container id
     * @param string $sPath  The file, as the container sees it
     * @param int    $iLimit The most of it to keep
     *
     * @return string|null
     */
    public static function readFile(string $sId, string $sPath, int $iLimit, string $sBudget): ?string
    {
        $aState = self::state();

        if ($aState['client'] === null || !self::withinBudget($sBudget)) {
            return null;
        }

        $sCommand = self::bounded($aState['client'], ['cp', '-L', $sId . ':' . $sPath, '-'], self::EXEC_TIMEOUT);
        $sTar     = @shell_exec($sCommand . ' 2>/dev/null | head -c ' . (int) ($iLimit + 1024));

        return is_string($sTar) ? self::untar($sTar, $iLimit) : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Whether a pass still has time left. The clock starts on the pass's first call.
     */
    public static function withinBudget(string $sBudget): bool
    {
        $fNow = hrtime(true) / 1e9;

        if (!isset(self::$aDeadlines[$sBudget])) {
            self::$aDeadlines[$sBudget] = $fNow + (self::BUDGETS[$sBudget] ?? 10);
        }

        return $fNow < self::$aDeadlines[$sBudget];
    }

    // --------------------------------------------------------------------------

    /**
     * Identifies the container a process runs in, if any
     *
     * The cgroup path is world-readable, needs no capability, and names the container by its full
     * id. Mount info is the fallback for a cgroup layout this does not recognise: Docker
     * bind-mounts each container's hostname, hosts and resolv.conf from a directory named for it.
     *
     * @return array{id: string, runtime: string}|null
     */
    public static function forProcess(int $iPid): ?array
    {
        $sDir    = '/proc/' . $iPid;
        $sCgroup = @file_get_contents($sDir . '/cgroup', false, null, 0, 65536);

        if (is_string($sCgroup)) {
            $aContainer = self::parseCgroup($sCgroup);
            if ($aContainer !== null) {
                return $aContainer;
            }
        }

        $sMounts = @file_get_contents($sDir . '/mountinfo', false, null, 0, 262144);

        return is_string($sMounts) ? self::parseMountinfo($sMounts) : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Picks a container id out of a `/proc/<pid>/cgroup` file
     *
     * - cgroup v2 under systemd: `0::/system.slice/docker-<id>.scope`
     * - cgroup v1, or the cgroupfs driver: `12:pids:/docker/<id>`
     * - containerd / Kubernetes: `cri-containerd-<id>.scope`, `/kubepods/…/<id>`
     * - Podman: `libpod-<id>.scope`
     *
     * A process in no container (`0::/user.slice/…`, `0::/init.scope`) is null.
     *
     * @return array{id: string, runtime: string}|null
     */
    public static function parseCgroup(string $sCgroup): ?array
    {
        $aPatterns = [
            self::RUNTIME_DOCKER     => ['#/docker-([0-9a-f]{64})\.scope#', '#/docker/([0-9a-f]{64})(?:/|$)#m'],
            self::RUNTIME_PODMAN     => ['#/libpod-([0-9a-f]{64})(?:\.scope)?#'],
            self::RUNTIME_CONTAINERD => ['#cri-containerd-([0-9a-f]{64})\.scope#', '#/kubepods[^\n]*/([0-9a-f]{64})(?:/|$)#m'],
        ];

        foreach ($aPatterns as $sRuntime => $aRegexes) {
            foreach ($aRegexes as $sRegex) {
                if (preg_match($sRegex, $sCgroup, $aMatch)) {
                    return ['id' => $aMatch[1], 'runtime' => $sRuntime];
                }
            }
        }

        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * Picks a Docker container id out of a `/proc/<pid>/mountinfo` file, from the files Docker
     * bind-mounts into every container out of `/var/lib/docker/containers/<id>/`
     *
     * @return array{id: string, runtime: string}|null
     */
    public static function parseMountinfo(string $sMounts): ?array
    {
        if (preg_match('#/docker/containers/([0-9a-f]{64})/(?:hostname|hosts|resolv\.conf)\s#', $sMounts, $aMatch)) {
            return ['id' => $aMatch[1], 'runtime' => self::RUNTIME_DOCKER];
        }

        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * Parses one line of `docker inspect` output, in the shape INSPECT_FIELDS asks for
     *
     * @return array<string, mixed>|null
     */
    public static function parseInspectLine(string $sLine): ?array
    {
        $aParts = explode("\t", rtrim($sLine, "\r\n"));
        $aKeys  = array_keys(self::INSPECT_FIELDS);

        if (count($aParts) !== count($aKeys)) {
            return null;
        }

        $aValues = [];
        foreach ($aKeys as $iIndex => $sKey) {
            $aValues[$sKey] = json_decode($aParts[$iIndex], true);
        }

        if (!is_string($aValues['id']) || !preg_match('/^[0-9a-f]{64}$/', $aValues['id'])) {
            return null;
        }

        $fString = fn(mixed $mValue): ?string => is_string($mValue) && trim($mValue) !== '' ? trim($mValue) : null;

        $sProject = $fString($aValues['compose_project']);
        $sService = $fString($aValues['compose_service']);

        return [
            'id'            => $aValues['id'],
            'name'          => ($sName = $fString($aValues['name'])) !== null ? ltrim($sName, '/') : null,
            'image'         => $fString($aValues['image']),
            'image_id'      => $fString($aValues['image_id']),
            'created'       => self::timestamp($fString($aValues['created'])),
            'started_at'    => self::timestamp($fString($aValues['started_at'])),
            'status'        => $fString($aValues['status']),
            'restart_count' => is_int($aValues['restart_count']) ? $aValues['restart_count'] : null,
            'compose'       => $sProject !== null || $sService !== null
                ? ['project' => $sProject, 'service' => $sService]
                : null,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Docker's nanosecond RFC 3339 (`2026-10-31T03:30:45.123456789Z`) cut to whole seconds.
     * Docker's zero time, for a container that has never started, is null.
     */
    private static function timestamp(?string $sValue): ?string
    {
        if ($sValue === null || str_starts_with($sValue, '0001-01-01')) {
            return null;
        }

        $sValue = preg_replace('/\.\d+(?=Z|[+-]\d{2}:\d{2}$)/', '', $sValue) ?? $sValue;

        try {
            return (new \DateTimeImmutable($sValue))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception) {
            return null;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Pulls the first regular file's contents out of a tar stream, as `docker cp … -` writes it
     */
    public static function untar(string $sTar, int $iLimit): ?string
    {
        if (strlen($sTar) < 512) {
            return null;
        }

        $sHeader = substr($sTar, 0, 512);
        $sType   = $sHeader[156];

        //  '0' or NUL is a regular file. Anything else — a link -L did not resolve, a directory — is
        //  not a file to read
        if ($sType !== '0' && $sType !== "\0") {
            return null;
        }

        $sSize = trim(substr($sHeader, 124, 12), " \0");
        if (!preg_match('/^[0-7]+$/', $sSize)) {
            return null;
        }

        return substr($sTar, 512, min(octdec($sSize), $iLimit));
    }

    // --------------------------------------------------------------------------

    /**
     * Words a failed `docker ps` the way someone fixing it would want to read it
     *
     * @param array<int, string> $aOutput
     */
    private static function daemonError(array $aOutput): string
    {
        $sOutput = strtolower(implode(' ', $aOutput));

        if (str_contains($sOutput, 'permission denied')) {
            return 'Docker socket not accessible: the heartbeat needs root or the docker group';
        }

        if (str_contains($sOutput, 'cannot connect') || str_contains($sOutput, 'no such file') || str_contains($sOutput, 'is the docker daemon running')) {
            return 'The Docker daemon is not running';
        }

        return 'The Docker daemon did not answer';
    }

    // --------------------------------------------------------------------------

    /**
     * Builds a docker invocation: bounded by `timeout`, and under an empty environment so an
     * inherited DOCKER_HOST cannot point it at another daemon
     *
     * @param array<int, string> $aArguments
     */
    private static function bounded(string $sClient, array $aArguments, int $iTimeout): string
    {
        $sCommand = escapeshellarg($sClient) . ' ' . implode(' ', array_map('escapeshellarg', $aArguments));

        if (System::commandExists('env')) {
            $sCommand = 'env -i ' . $sCommand;
        }

        if (System::commandExists('timeout')) {
            $sCommand = 'timeout ' . $iTimeout . ' ' . $sCommand;
        }

        return $sCommand;
    }

    // --------------------------------------------------------------------------

    /**
     * Runs a command, keeping stderr (it is where docker explains itself) and capping the output.
     * The exit status is the command's own, not the cap's.
     *
     * @return array{exit: int, output: array<int, string>}
     */
    private static function run(string $sCommand): array
    {
        $sOutput = '';
        $iExit   = 1;

        $rProcess = @proc_open($sCommand . ' 2>&1', [1 => ['pipe', 'w']], $aPipes);

        if (!is_resource($rProcess)) {
            return ['exit' => 1, 'output' => []];
        }

        while (!feof($aPipes[1]) && strlen($sOutput) <= self::OUTPUT_LIMIT) {
            $sChunk = fread($aPipes[1], 8192);
            if ($sChunk === false) {
                break;
            }
            $sOutput .= $sChunk;
        }

        fclose($aPipes[1]);
        $iExit = proc_close($rProcess);

        $sOutput = substr($sOutput, 0, self::OUTPUT_LIMIT);
        $aLines  = $sOutput === '' ? [] : explode("\n", rtrim($sOutput, "\n"));

        return ['exit' => $iExit, 'output' => $aLines];
    }
}
