<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Helper\Redact;
use Shed\Cli\Helper\System;

/**
 * Class Node
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Node implements \JsonSerializable
{
    /**
     * Where a system-wide node might be installed.
     *
     * Entries containing a wildcard are globbed. `n` and, on some images, nvm
     * install below /usr/local rather than into a home directory, so they are
     * treated as system installs — what makes an install "system" here is that
     * it sits outside anybody's home, not which tool put it there.
     *
     * @var array
     */
    private const SYSTEM_PATHS = [
        '/usr/bin/node',
        '/usr/bin/nodejs',
        '/usr/local/bin/node',
        '/usr/local/bin/nodejs',
        '/opt/node/bin/node',
        '/snap/bin/node',
        '/usr/local/n/versions/node/*/bin/node',
        '/usr/local/nvm/versions/node/*/bin/node',
        '/opt/nvm/versions/node/*/bin/node',
    ];

    /**
     * Where each per-user version manager keeps its node binaries, relative to
     * the user's home directory. The wildcard is the version.
     *
     * nvm is the one we actually deploy with; the others are here because a
     * developer who has moved on from nvm leaves a working node behind that
     * would otherwise go unreported.
     *
     * @var array
     */
    private const USER_MANAGER_PATHS = [
        'nvm'    => ['.nvm/versions/node/*/bin/node'],
        'fnm'    => [
            '.local/share/fnm/node-versions/*/installation/bin/node',
            '.fnm/node-versions/*/installation/bin/node',
        ],
        'volta'  => ['.volta/tools/image/node/*/bin/node'],
        'asdf'   => ['.asdf/installs/nodejs/*/bin/node'],
        'mise'   => ['.local/share/mise/installs/node/*/bin/node'],
        'nodenv' => ['.nodenv/versions/*/bin/node'],
        'n'      => ['n/versions/node/*/bin/node'],
    ];

    /**
     * Identifies the manager responsible for a node binary, and the version it
     * holds, from the path alone.
     *
     * Every one of these lays its versions out as a directory named for the
     * version, which is the only version available for an install the heartbeat
     * declines to execute — see readVersion(). A path-derived version is
     * therefore only as trustworthy as whoever owns the path, which is why
     * `version_source` is reported alongside it.
     *
     * The optional dot covers both the dotted home directory form (~/.nvm) and
     * the shared form (/usr/local/nvm).
     *
     * @var array
     */
    private const PATH_MANAGERS = [
        'nvm'    => '#/\.?nvm/versions/node/([^/]+)/#',
        'fnm'    => '#/\.?fnm/node-versions/([^/]+)/installation/#',
        'volta'  => '#/\.?volta/tools/image/node/([^/]+)/#',
        'asdf'   => '#/\.?asdf/installs/nodejs/([^/]+)/#',
        'mise'   => '#/\.?mise/installs/node/([^/]+)/#',
        'nodenv' => '#/\.?nodenv/versions/([^/]+)/#',
        'n'      => '#/n/versions/node/([^/]+)/#',
    ];

    /**
     * What a node version looks like, with or without nvm's leading `v`
     *
     * @var string
     */
    private const VERSION = '/^v?\d+\.\d+\.\d+/';

    /**
     * Where a reported version came from. A version read from the binary is a
     * statement by node itself; one taken from the path is a statement by
     * whoever laid the path out, which for a home directory is its owner.
     *
     * @var string
     */
    private const VERSION_SOURCE_BINARY = 'binary';
    private const VERSION_SOURCE_PATH   = 'path';

    /**
     * The binary names node is installed under. Debian shipped it as `nodejs`
     * for years, and the name survives in older images.
     *
     * @var array
     */
    private const BINARIES = ['node', 'nodejs'];

    /**
     * Where nvm keeps the aliases which decide the version a shell picks up
     *
     * @var string
     */
    private const NVM_ALIAS_DIR = 'alias';

    /**
     * The aliases which mean "the newest version installed"
     *
     * @var array
     */
    private const NVM_ALIASES_LATEST = ['node', 'stable', '*'];

    /**
     * nvm's name for "whichever node is on the PATH", i.e. not one of its own
     *
     * @var string
     */
    private const NVM_SYSTEM = 'system';

    /**
     * How many links of an nvm alias chain to follow before giving up.
     * `default` -> `lts/*` -> `lts/iron` -> `v20.11.1` is three, so this leaves
     * room whilst still terminating on a cycle.
     *
     * @var int
     */
    private const NVM_ALIAS_HOPS = 8;

    /**
     * The files a user's shell reads on login, in which nvm installs itself.
     * Their presence is what decides whether an nvm install is actually in use.
     *
     * @var array
     */
    private const SHELL_INIT_FILES = [
        '.bashrc',
        '.bash_profile',
        '.profile',
        '.zshrc',
        '.zprofile',
        '.zshenv',
    ];

    /**
     * Home directories which exist but belong to no real user
     *
     * @var array
     */
    private const HOMES_IGNORED = ['/', '/dev/null', '/nonexistent', '/bin', '/sbin', '/usr/sbin'];

    /**
     * Caps, applied so that a pathological host cannot inflate the payload
     *
     * @var int
     */
    private const LIMIT_VERSIONS  = 50;
    private const LIMIT_ALIASES   = 50;
    private const LIMIT_PROCESSES = 50;
    private const LIMIT_CMDLINE   = 512;

    /**
     * How long a version lookup is given before it is abandoned
     *
     * @var int
     */
    private const EXEC_TIMEOUT = 5;

    /**
     * How much of a shell init file to read when looking for nvm's loader.
     * nvm appends itself to the end, but a truncated read would miss it, so
     * this is generous enough to cover a heavily customised profile.
     *
     * @var int
     */
    private const READ_LIMIT_INIT = 131072;

    /**
     * How much of an nvm alias file to read. They hold a single short line.
     *
     * @var int
     */
    private const READ_LIMIT_ALIAS = 256;

    /**
     * How much of a package.json to read when looking for its version
     *
     * @var int
     */
    private const READ_LIMIT_PACKAGE = 2048;

    /**
     * Where the Debian npm package puts itself, which is not where any other
     * distribution of node puts it
     *
     * @var string
     */
    private const NPM_DEBIAN = '/usr/share/nodejs/npm/package.json';

    /**
     * Versions already established for a given path, so that a host running
     * twenty processes off one binary only pays for one lookup
     *
     * @var array
     */
    private array $aVersionCache = [];

    // --------------------------------------------------------------------------

    /**
     * Gathers details about the node installations on the host, and about which
     * of them are in use.
     *
     * Node is reported in three parts because there are three separate
     * questions: what a shell gets by default (`system`), what each user has
     * installed for themselves (`users`), and what is actually executing right
     * now (`running`). A host can easily have a system node which nothing uses,
     * whilst every running service is on a version nvm installed into a home
     * directory, so neither of the first two answers the third.
     *
     * `users` and the maps beneath `running` are keyed, so they are cast to
     * objects to keep them serialising as `{}` rather than `[]` when empty — an
     * empty PHP array is indistinguishable from an empty list, which changes
     * the type the receiving API sees.
     *
     * @return array|null
     */
    public function get(): ?array
    {
        switch (Os::getType()) {
            case Os::LINUX:
                $aUsers = $this->getPasswd();

                return [
                    'system'  => $this->getSystem(),
                    'users'   => (object) $this->getUserInstalls($aUsers),
                    'running' => $this->getRunning($aUsers),
                ];

            case Os::MACOS:
                //  Not a deploy target, and the process enumeration below reads
                //  /proc, which macOS does not have
                return null;

            default:
                throw new HeartbeatException('Unable to determine node status.');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Describes the node available to every user on the host
     *
     * `path` and `version` describe the binary a shell would actually resolve,
     * which is not necessarily the packaged one: a tarball unpacked into
     * /usr/local/bin shadows /usr/bin/node whilst leaving it in place, and only
     * the full `installs` list shows that has happened.
     *
     * `on_path` is resolved against the PATH the heartbeat itself inherited,
     * which under cron is a good deal shorter than a login shell's. Everything
     * in SYSTEM_PATHS is reported either way, so an install cron cannot see is
     * still listed — just not marked as the one on the PATH.
     *
     * @return array
     */
    private function getSystem(): array
    {
        $aInstalls = [];
        $sOnPath   = $this->getPathBinary();

        foreach ($this->getSystemBinaries($sOnPath) as $sPath) {

            $sReal    = realpath($sPath) ?: $sPath;
            $aVersion = $this->readVersion($sPath);
            $aManager = $this->getManager($sReal);

            $aInstalls[] = [
                'path'           => $sPath,
                'real_path'      => $sReal,
                'version'        => $aVersion['version'],
                'version_source' => $aVersion['version_source'],
                'manager'        => $aManager['manager'] ?? 'system',
                'package'        => $this->getPackage($sReal),
                'on_path'        => $sOnPath !== null && $sPath === $sOnPath,
                'error'          => $aVersion['error'],
            ];
        }

        //  The effective install is whichever one a shell resolves; where node
        //  is not on the PATH at all, the first found still says what is there
        $aEffective = null;
        foreach ($aInstalls as $aInstall) {
            if ($aInstall['on_path']) {
                $aEffective = $aInstall;
                break;
            }
        }

        $aEffective = $aEffective ?? ($aInstalls[0] ?? null);

        return [
            'present'     => !empty($aInstalls),
            'on_path'     => $sOnPath !== null,
            'path'        => $aEffective['path'] ?? null,
            'version'     => $aEffective['version'] ?? null,
            'npm_version' => $aEffective !== null ? $this->getNpmVersion($aEffective['real_path']) : null,
            'installs'    => $aInstalls,
            'error'       => empty($aInstalls) ? 'No system-wide node installation found' : null,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Resolves node against the PATH the heartbeat itself is running with
     *
     * @return string|null
     */
    private function getPathBinary(): ?string
    {
        foreach (self::BINARIES as $sBinary) {
            try {

                //  `command -v` is a shell builtin, so it reports the resolution
                //  the shell would perform rather than which(1)'s opinion of it
                $sPath = trim(System::execString('command -v ' . escapeshellarg($sBinary) . ' 2>/dev/null || true'));

                if ($sPath !== '' && str_starts_with($sPath, '/') && is_file($sPath)) {
                    return $sPath;
                }

            } catch (CommandFailedException) {
            }
        }

        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * Lists the node binaries installed outside of any home directory
     *
     * @param string|null $sOnPath The binary the PATH resolves to, listed first
     *
     * @return array
     */
    private function getSystemBinaries(?string $sOnPath): array
    {
        $aPaths = [];

        if ($sOnPath !== null) {
            $aPaths[] = $sOnPath;
        }

        foreach (self::SYSTEM_PATHS as $sCandidate) {

            if (str_contains($sCandidate, '*')) {
                foreach (glob($sCandidate) ?: [] as $sPath) {
                    $aPaths[] = $sPath;
                }
                continue;
            }

            $aPaths[] = $sCandidate;
        }

        $aFound = [];
        $aSeen  = [];

        foreach ($aPaths as $sPath) {

            if (!is_file($sPath)) {
                continue;
            }

            //  Deduplicated on the resolved target, so that node and nodejs
            //  pointing at one binary are reported once, under the name the
            //  PATH found first
            $sKey = realpath($sPath) ?: $sPath;

            if (isset($aSeen[$sKey])) {
                continue;
            }

            $aSeen[$sKey] = true;
            $aFound[]     = $sPath;
        }

        return $aFound;
    }

    // --------------------------------------------------------------------------

    /**
     * Reports the npm which ships alongside a node install
     *
     * Read from npm's own package.json rather than by running `npm --version`,
     * which would mean executing several megabytes of JavaScript to learn one
     * string.
     *
     * @param string $sNode The node binary npm is to be located against
     *
     * @return string|null
     */
    private function getNpmVersion(string $sNode): ?string
    {
        //  <prefix>/bin/node -> <prefix>/lib/node_modules/npm, which covers the
        //  official builds, nodesource and everything nvm installs. Debian
        //  packages npm separately and puts it somewhere else entirely
        $aCandidates = [
            dirname($sNode, 2) . '/lib/node_modules/npm/package.json',
            self::NPM_DEBIAN,
        ];

        foreach ($aCandidates as $sPath) {

            if (!is_file($sPath) || !is_readable($sPath)) {
                continue;
            }

            $sContents = @file_get_contents($sPath, false, null, 0, self::READ_LIMIT_PACKAGE);
            if ($sContents === false) {
                continue;
            }

            //  A partial read leaves invalid JSON behind, so the version is
            //  matched out of it directly; by convention it sits within the
            //  first few lines
            if (preg_match('/"version"\s*:\s*"([^"]+)"/', $sContents, $aMatches)) {
                return $aMatches[1];
            }
        }

        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * Reports the package a node binary was installed by, where it was
     * installed by one at all
     *
     * @param string $sPath The binary to look up
     *
     * @return array|null
     */
    private function getPackage(string $sPath): ?array
    {
        if (!System::commandExists('dpkg-query')) {
            return null;
        }

        try {

            //  -S exits non-zero for anything dpkg did not put there, which is
            //  the common case for a tarball or nvm install
            $sPackage = trim(System::execString(
                'dpkg-query -S ' . escapeshellarg($sPath) . ' 2>/dev/null || true'
            ));

        } catch (CommandFailedException) {
            return null;
        }

        //  "nodejs: /usr/bin/node", or "diversion by X from: ..." for a diverted
        //  path, which carries no package name worth reporting
        if ($sPackage === '' || !preg_match('/^([^:\s]+):/', $sPackage, $aMatches)) {
            return null;
        }

        $sName = $aMatches[1];

        try {

            $sVersion = trim(System::execString(
                'dpkg-query -W -f=\'${Version}\' ' . escapeshellarg($sName) . ' 2>/dev/null || true'
            ));

        } catch (CommandFailedException) {
            $sVersion = '';
        }

        return [
            'name'    => $sName,
            'version' => $sVersion !== '' ? $sVersion : null,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Reads the user database
     *
     * `getent` is preferred over reading /etc/passwd so that users provided by
     * a directory service are seen too; the file is the fallback.
     *
     * @return array Keyed by uid
     */
    private function getPasswd(): array
    {
        $aOutput = [];

        try {

            System::exec('getent passwd 2>/dev/null || cat /etc/passwd', $aOutput);

        } catch (CommandFailedException) {
            return [];
        }

        $aUsers = [];

        foreach ($aOutput as $sLine) {

            //  name:password:uid:gid:gecos:home:shell
            $aParts = explode(':', $sLine);

            if (count($aParts) < 7 || !ctype_digit($aParts[2])) {
                continue;
            }

            $iUid = (int) $aParts[2];

            //  A uid may appear more than once where an alias account exists;
            //  the first is the canonical one
            if (isset($aUsers[$iUid])) {
                continue;
            }

            $aUsers[$iUid] = [
                'name'  => $aParts[0],
                'uid'   => $iUid,
                'home'  => rtrim($aParts[5], '/') ?: $aParts[5],
                'shell' => $aParts[6],
            ];
        }

        return $aUsers;
    }

    // --------------------------------------------------------------------------

    /**
     * Finds the node installations belonging to individual users
     *
     * Users with nothing installed are omitted rather than reported empty; on a
     * stock image that is every account on the host bar one.
     *
     * @param array $aUsers The user database, as returned by getPasswd()
     *
     * @return array Keyed by username
     */
    private function getUserInstalls(array $aUsers): array
    {
        $aResults = [];
        $aSeen    = [];

        foreach ($aUsers as $aUser) {

            $sHome = $aUser['home'];

            if ($sHome === '' || in_array($sHome, self::HOMES_IGNORED, true) || !is_dir($sHome)) {
                continue;
            }

            //  Accounts commonly share a home directory (sync, games, ...);
            //  scanning it once avoids reporting the same install per account
            $sKey = realpath($sHome) ?: $sHome;
            if (isset($aSeen[$sKey])) {
                continue;
            }
            $aSeen[$sKey] = true;

            $aInstalls = $this->getManagedInstalls($sHome);
            $aNvm      = $this->getNvm($aUser);

            if (empty($aInstalls) && $aNvm === null) {
                continue;
            }

            //  Mark the version the user's shell would actually select
            $sDefault = $aNvm['default_version'] ?? null;
            foreach ($aInstalls as &$aInstall) {
                $aInstall['default'] = $aInstall['manager'] === 'nvm'
                    && $sDefault !== null
                    && $aInstall['version'] === $sDefault;
            }
            unset($aInstall);

            $aResults[$aUser['name']] = [
                'uid'      => $aUser['uid'],
                'home'     => $sHome,
                'shell'    => $aUser['shell'],
                'installs' => $aInstalls,
                'nvm'      => $aNvm,
            ];
        }

        return $aResults;
    }

    // --------------------------------------------------------------------------

    /**
     * Lists the node binaries a version manager has installed into a home
     * directory
     *
     * @param string $sHome The home directory to scan
     *
     * @return array
     */
    private function getManagedInstalls(string $sHome): array
    {
        $aInstalls = [];

        foreach (self::USER_MANAGER_PATHS as $sManager => $aPatterns) {
            foreach ($aPatterns as $sPattern) {
                foreach (glob($sHome . '/' . $sPattern) ?: [] as $sPath) {

                    if (count($aInstalls) >= self::LIMIT_VERSIONS) {
                        return $aInstalls;
                    }

                    if (!is_file($sPath)) {
                        continue;
                    }

                    $aVersion = $this->readVersion($sPath);

                    $aInstalls[] = [
                        'manager'        => $sManager,
                        'version'        => $aVersion['version'],
                        'version_source' => $aVersion['version_source'],
                        'path'           => $sPath,
                        'default'        => false,
                        'error'          => $aVersion['error'],
                    ];
                }
            }
        }

        return $aInstalls;
    }

    // --------------------------------------------------------------------------

    /**
     * Describes a user's nvm installation
     *
     * The installed versions alone do not say which one the user gets: that is
     * decided by the `default` alias, which is frequently a moving target such
     * as `lts/*`. Both the alias and the version it currently resolves to are
     * reported, as the first explains the second changing without anybody
     * having touched the host.
     *
     * @param array $aUser The user, as returned by getPasswd()
     *
     * @return array|null Null where the user has no nvm
     */
    private function getNvm(array $aUser): ?array
    {
        $sDir = $aUser['home'] . '/.nvm';

        if (!is_dir($sDir)) {
            return null;
        }

        $aVersions = $this->getNvmVersions($sDir);
        $aAliases  = $this->getNvmAliases($sDir, $aUser);
        $sDefault  = $aAliases['default'] ?? null;
        $sVersion  = $this->resolveNvmAlias($sDefault, $aVersions, $aAliases);

        return [
            'path'              => $sDir,
            'versions'          => $aVersions,
            'aliases'           => (object) $aAliases,
            'default'           => $sDefault,
            'default_version'   => $sVersion,
            //  An alias may name a version which is not installed, which is what
            //  happens when a moving target such as lts/* rolls forward. nvm then
            //  selects nothing and the shell falls back to the system node, so
            //  this is the difference between a default and an effective default
            'default_installed' => $sVersion !== null && $sVersion !== self::NVM_SYSTEM
                ? in_array($sVersion, $aVersions, true)
                : null,
            'current'           => $this->getNvmCurrent($sDir),
            'initialised'       => $this->isNvmInitialised($aUser),
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Lists the versions installed under an nvm directory, newest first
     *
     * @param string $sDir The nvm directory
     *
     * @return array
     */
    private function getNvmVersions(string $sDir): array
    {
        $aVersions = [];

        foreach (glob($sDir . '/versions/node/*/bin/node') ?: [] as $sPath) {

            //  <dir>/versions/node/<version>/bin/node
            $sVersion = basename(dirname($sPath, 2));

            if (preg_match(self::VERSION, $sVersion)) {
                $aVersions[] = $sVersion;
            }
        }

        usort($aVersions, fn(string $sA, string $sB): int => version_compare($sB, $sA));

        return array_slice($aVersions, 0, self::LIMIT_VERSIONS);
    }

    // --------------------------------------------------------------------------

    /**
     * Reads the aliases a user has defined, including the lts/* set nvm
     * maintains for itself
     *
     * @param string $sDir  The nvm directory
     * @param array  $aUser The user it belongs to
     *
     * @return array
     */
    private function getNvmAliases(string $sDir, array $aUser): array
    {
        $sAliasDir = $sDir . '/' . self::NVM_ALIAS_DIR;

        if (!is_dir($sAliasDir)) {
            return [];
        }

        $aAliases = [];

        //  One level of nesting, which is where nvm keeps lts/argon, lts/iron
        //  and the lts/* pointer at the newest of them
        foreach (glob($sAliasDir . '/{*,*/*}', GLOB_BRACE) ?: [] as $sPath) {

            if (count($aAliases) >= self::LIMIT_ALIASES) {
                break;
            }

            if (!is_file($sPath)) {
                continue;
            }

            $sContents = $this->readUserFile($sPath, $aUser, self::READ_LIMIT_ALIAS);
            if ($sContents === null) {
                continue;
            }

            $sName = substr($sPath, strlen($sAliasDir) + 1);
            $sName = trim($sName);

            if ($sName !== '') {
                $aAliases[$sName] = trim($sContents);
            }
        }

        return $aAliases;
    }

    // --------------------------------------------------------------------------

    /**
     * Follows an alias to the version it names
     *
     * An alias may point at another alias (`default` -> `lts/*` -> `lts/iron`),
     * at a whole or partial version (`v20.11.1`, `20`), or at one of the names
     * meaning "the newest installed".
     *
     * A whole version is returned whether or not it is installed, since that
     * distinction is reported separately and an alias pointing at a version
     * which has gone is worth seeing. A partial one can only be resolved against
     * what is installed, so it yields nothing when nothing matches.
     *
     * @param string|null $sAlias    The alias to resolve
     * @param array       $aVersions The installed versions, newest first
     * @param array       $aAliases  Every alias defined, for following chains
     *
     * @return string|null
     */
    private function resolveNvmAlias(?string $sAlias, array $aVersions, array $aAliases): ?string
    {
        $sValue = $sAlias !== null ? trim($sAlias) : '';

        for ($i = 0; $i < self::NVM_ALIAS_HOPS; $i++) {

            if ($sValue === '') {
                return null;
            }

            //  nvm's own escape hatch back to whatever is on the PATH
            if ($sValue === self::NVM_SYSTEM) {
                return self::NVM_SYSTEM;
            }

            if (in_array($sValue, self::NVM_ALIASES_LATEST, true)) {
                return $aVersions[0] ?? null;
            }

            if (preg_match('/^v?\d+\.\d+\.\d+$/', $sValue)) {
                return 'v' . ltrim($sValue, 'v');
            }

            //  A partial version ends the chain either way
            if (preg_match('/^v?\d+(\.\d+)?$/', $sValue)) {
                return $this->matchVersion($sValue, $aVersions);
            }

            if (!array_key_exists($sValue, $aAliases)) {
                return null;
            }

            $sValue = trim($aAliases[$sValue]);
        }

        //  A chain this long is a loop
        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * Finds the newest installed version matching a whole or partial version
     *
     * @param string $sSpec     The version to match, e.g. "20" or "v20.11.1"
     * @param array  $aVersions The installed versions, newest first
     *
     * @return string|null
     */
    private function matchVersion(string $sSpec, array $aVersions): ?string
    {
        $sSpec = 'v' . ltrim($sSpec, 'v');

        foreach ($aVersions as $sVersion) {
            if ($sVersion === $sSpec || str_starts_with($sVersion, $sSpec . '.')) {
                return $sVersion;
            }
        }

        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * Reports the version the `current` symlink points at
     *
     * The symlink only exists where NVM_SYMLINK_CURRENT is set, which is not the
     * default, so its absence says nothing.
     *
     * @param string $sDir The nvm directory
     *
     * @return string|null
     */
    private function getNvmCurrent(string $sDir): ?string
    {
        $sCurrent = $sDir . '/current';

        if (!is_link($sCurrent)) {
            return null;
        }

        $sTarget  = (string) readlink($sCurrent);
        $aManager = $this->getManager($sTarget);

        return $aManager['version'] ?? null;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether nvm is loaded by the user's shell
     *
     * An nvm directory nobody sources is inert — the user still gets whatever is
     * on the PATH — so this is what separates an installation from one in use.
     *
     * @param array $aUser The user to inspect
     *
     * @return bool
     */
    private function isNvmInitialised(array $aUser): bool
    {
        foreach (self::SHELL_INIT_FILES as $sFile) {

            $sPath     = $aUser['home'] . '/' . $sFile;
            $sContents = $this->readUserFile($sPath, $aUser, self::READ_LIMIT_INIT);

            if ($sContents === null) {
                continue;
            }

            if (str_contains($sContents, 'NVM_DIR') || str_contains($sContents, 'nvm.sh')) {
                return true;
            }
        }

        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Reads a file out of a user's home directory
     *
     * The heartbeat runs as root, so a symlink planted in a home directory would
     * otherwise be followed into anything on the host and its contents posted to
     * the API. Two things prevent that: the resolved path must still be inside
     * the home directory it was reached through, and the file must belong either
     * to that user or to root.
     *
     * @param string $sPath      The file to read
     * @param array  $aUser      The user whose home it must lie within
     * @param int    $iMaxLength How much of it to read
     *
     * @return string|null Null where the file is absent, unreadable or untrusted
     */
    private function readUserFile(string $sPath, array $aUser, int $iMaxLength): ?string
    {
        $sReal = realpath($sPath);
        $sHome = realpath($aUser['home']);

        if ($sReal === false || $sHome === false) {
            return null;
        }

        if (!str_starts_with($sReal, rtrim($sHome, '/') . '/')) {
            return null;
        }

        if (!is_file($sReal)) {
            return null;
        }

        $iOwner = @fileowner($sReal);
        if ($iOwner === false || ($iOwner !== $aUser['uid'] && $iOwner !== 0)) {
            return null;
        }

        $sContents = @file_get_contents($sReal, false, null, 0, $iMaxLength);

        return $sContents !== false ? $sContents : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Lists the node processes running on the host, and who is running them
     *
     * This is the only part of the report which says what is actually in use.
     * It reads /proc rather than parsing ps(1) output, so a process is
     * attributed to the binary its kernel image was loaded from — which is the
     * version genuinely executing, whatever the PATH, the shell profile or a
     * process manager's configuration might imply.
     *
     * @param array $aUsers The user database, as returned by getPasswd()
     *
     * @return array
     */
    private function getRunning(array $aUsers): array
    {
        if (!is_dir('/proc')) {
            return $this->runningShape('/proc is not available on this host');
        }

        $aPids = scandir('/proc');
        if ($aPids === false) {
            return $this->runningShape('Failed to list /proc');
        }

        $aProcesses  = [];
        $aByUser     = [];
        $iCount      = 0;
        $iUnreadable = 0;

        foreach ($aPids as $sPid) {

            if (!ctype_digit($sPid)) {
                continue;
            }

            $aProcess = $this->getProcess((int) $sPid, $aUsers, $iUnreadable);
            if ($aProcess === null) {
                continue;
            }

            $iCount++;

            //  Counted before the cap is applied, so that the summary stays
            //  accurate even where the detail is truncated
            $sUser    = $aProcess['user'] ?? 'uid:' . $aProcess['uid'];
            $sVersion = $aProcess['version'] ?? 'unknown';

            $aByUser[$sUser][$sVersion] = ($aByUser[$sUser][$sVersion] ?? 0) + 1;

            if (count($aProcesses) < self::LIMIT_PROCESSES) {
                $aProcesses[] = $aProcess;
            }
        }

        foreach ($aByUser as $sUser => $aVersions) {
            $aByUser[$sUser] = (object) $aVersions;
        }

        return [
            'count'      => $iCount,
            'by_user'    => (object) $aByUser,
            'processes'  => $aProcesses,
            'truncated'  => $iCount > count($aProcesses),
            //  Processes which could not be inspected at all. Zero on a normal
            //  host; anything else means this list is not the whole picture
            'unreadable' => $iUnreadable,
            'error'      => null,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * The shape the running processes are reported in, holding nothing
     *
     * @param string|null $sError Why nothing could be established
     *
     * @return array
     */
    private function runningShape(?string $sError): array
    {
        return [
            'count'      => null,
            'by_user'    => (object) [],
            'processes'  => [],
            'truncated'  => false,
            'unreadable' => 0,
            'error'      => $sError,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Describes a single process, where it turns out to be node
     *
     * The executable is read from /proc/<pid>/exe, which is the process' own
     * account of what it is running and cannot be spoofed by a process manager
     * or a renamed script. Reading it for another user's process needs
     * CAP_SYS_PTRACE, which root holds on a normal host but is dropped by
     * default inside a container; where it is missing the process is identified
     * from the world-readable /proc/<pid>/comm and /proc/<pid>/cmdline instead,
     * and counted so that a partial view is never mistaken for a quiet one.
     *
     * @param int   $iPid        The process to inspect
     * @param array $aUsers      The user database, as returned by getPasswd()
     * @param int   $iUnreadable Incremented for each process which could not be
     *                           identified either way
     *
     * @return array|null Null where the process is not node, or has since exited
     */
    private function getProcess(int $iPid, array $aUsers, int &$iUnreadable): ?array
    {
        $sDir = '/proc/' . $iPid;
        $sExe = @readlink($sDir . '/exe');

        $bDeleted = false;
        $sPath    = null;
        $sError   = null;

        if (is_string($sExe) && $sExe !== '') {

            //  The kernel appends this where the binary has been replaced or
            //  removed since the process started, which is worth knowing: the
            //  version on disk is then not necessarily the version running
            $bDeleted = str_ends_with($sExe, ' (deleted)');
            $sPath    = $bDeleted ? substr($sExe, 0, -10) : $sExe;

            if (!in_array(basename($sPath), self::BINARIES, true)) {
                return null;
            }

        } else {

            //  Kernel threads have no executable and processes exit underneath
            //  the scandir, so an unreadable link is only interesting where the
            //  process is still there to be read
            if (!is_dir($sDir)) {
                return null;
            }

            if (!$this->isProcessNode($sDir)) {
                $iUnreadable++;
                return null;
            }

            $sPath  = $this->getProcessBinaryFromCommand($sDir);
            $sError = 'Unable to read ' . $sDir . '/exe - CAP_SYS_PTRACE is required to inspect'
                . ' another user\'s process';
        }

        $iUid  = @fileowner($sDir);
        $iUid  = $iUid !== false ? $iUid : null;
        $sUser = $iUid !== null ? ($aUsers[$iUid]['name'] ?? null) : null;

        $aManager = $sPath !== null ? $this->getManager($sPath) : null;
        $aVersion = $this->getProcessVersion($sPath, $bDeleted, $aManager);

        return [
            'pid'            => $iPid,
            'uid'            => $iUid,
            'user'           => $sUser,
            'version'        => $aVersion['version'],
            'version_source' => $aVersion['version_source'],
            'path'           => $sPath,
            'manager'        => $sPath !== null ? ($aManager['manager'] ?? 'system') : null,
            'deleted'        => $bDeleted,
            'started'        => $this->getProcessStart($sDir),
            'command'        => $this->getProcessCommand($sDir),
            'error'          => $sError ?? $aVersion['error'],
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Establishes the version a process is running
     *
     * @param string|null $sPath     The binary it was loaded from, where known
     * @param bool        $bDeleted  Whether that binary is still the one on disk
     * @param array|null  $aManager  The manager owning the path, where any does
     *
     * @return array{version: string|null, version_source: string|null, error: string|null}
     */
    private function getProcessVersion(?string $sPath, bool $bDeleted, ?array $aManager): array
    {
        if ($sPath === null) {
            return $this->versionError('The binary this process was started from could not be established');
        }

        if (!$bDeleted) {
            return $this->readVersion($sPath);
        }

        //  Whatever is at the path now is not what this process is running, so
        //  it is not executed; only the path itself still says anything
        $sVersion = $aManager['version'] ?? null;

        return [
            'version'        => $sVersion,
            'version_source' => $sVersion !== null ? self::VERSION_SOURCE_PATH : null,
            'error'          => 'The binary has been replaced or removed since the process started',
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a process is node without reading its executable
     *
     * Both sources can be rewritten by the process itself — node does exactly
     * that when an application sets process.title — so this identifies fewer
     * processes than the executable link does. It is a fallback, not an
     * equivalent.
     *
     * @param string $sDir The process' /proc directory
     *
     * @return bool
     */
    private function isProcessNode(string $sDir): bool
    {
        $sComm = @file_get_contents($sDir . '/comm', false, null, 0, 64);

        if (is_string($sComm) && in_array(trim($sComm), self::BINARIES, true)) {
            return true;
        }

        $sBinary = $this->getProcessBinaryFromCommand($sDir);

        return $sBinary !== null;
    }

    // --------------------------------------------------------------------------

    /**
     * Recovers the binary a process was started with from its arguments
     *
     * Only an absolute path is taken. A bare `node` says nothing about which
     * node, which is the whole question here.
     *
     * @param string $sDir The process' /proc directory
     *
     * @return string|null
     */
    private function getProcessBinaryFromCommand(string $sDir): ?string
    {
        $sCommand = @file_get_contents($sDir . '/cmdline', false, null, 0, self::LIMIT_CMDLINE);

        if (!is_string($sCommand) || $sCommand === '') {
            return null;
        }

        $aArguments = explode("\0", $sCommand);
        $sBinary    = $aArguments[0] ?? '';

        if (!str_starts_with($sBinary, '/') || !in_array(basename($sBinary), self::BINARIES, true)) {
            return null;
        }

        return $sBinary;
    }

    // --------------------------------------------------------------------------

    /**
     * Reports when a process started
     *
     * The timestamps on a /proc entry are those of the process it describes, so
     * the directory's mtime is its start time.
     *
     * @param string $sDir The process' /proc directory
     *
     * @return string|null
     */
    private function getProcessStart(string $sDir): ?string
    {
        $iStarted = @filemtime($sDir);

        return $iStarted !== false
            ? (new \DateTimeImmutable('@' . $iStarted))->format(DATE_ATOM)
            : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Reports the command a process was started with
     *
     * The arguments are what identify the application — the binary is `node` on
     * every one of them — but they are also where a token or a database password
     * ends up on a host where somebody has been careless, so they are redacted
     * on the way out.
     *
     * @param string $sDir The process' /proc directory
     *
     * @return string|null
     */
    private function getProcessCommand(string $sDir): ?string
    {
        $sCommand = @file_get_contents($sDir . '/cmdline', false, null, 0, self::LIMIT_CMDLINE);

        if ($sCommand === false || $sCommand === '') {
            return null;
        }

        $bTruncated = strlen($sCommand) >= self::LIMIT_CMDLINE;

        //  The arguments are NUL separated, and the string is NUL terminated
        $sCommand = trim(str_replace("\0", ' ', $sCommand));

        if ($sCommand === '') {
            return null;
        }

        //  Redacted before the marker is appended, so that a secret cut in half
        //  by the read limit is still matched by the patterns
        return Redact::secrets($sCommand) . ($bTruncated ? '...' : '');
    }

    // --------------------------------------------------------------------------

    /**
     * Identifies the version manager which owns a path, and the version it holds
     *
     * @param string $sPath The path to inspect
     *
     * @return array|null Null where no manager recognises it
     */
    private function getManager(string $sPath): ?array
    {
        foreach (self::PATH_MANAGERS as $sManager => $sPattern) {

            if (!preg_match($sPattern, $sPath, $aMatches)) {
                continue;
            }

            $sVersion = $aMatches[1];

            //  asdf and mise both allow an install to be named for something
            //  other than its version, e.g. "lts", which is not one
            if (!preg_match(self::VERSION, $sVersion)) {
                return ['manager' => $sManager, 'version' => null];
            }

            return [
                'manager' => $sManager,
                //  nvm's directories carry the leading v and the others' do not;
                //  normalised so that one version reads the same everywhere
                'version' => 'v' . ltrim($sVersion, 'v'),
            ];
        }

        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * Establishes the version of a node binary
     *
     * Asking the binary is the only authoritative answer, so it is asked
     * wherever it is safe to do so — which means only when root owns every
     * component of the path leading to it, and none of them is writable by
     * anybody else. The heartbeat runs as root, and a node in a user's home
     * directory is a file that user controls; running it to ask its version
     * would hand them root.
     *
     * Everything a version manager installs therefore goes unexecuted, and is
     * reported from the version its directory is named for instead. That is a
     * claim by whoever owns the directory rather than by node, which is what
     * `version_source` records.
     *
     * @param string $sPath The binary to inspect
     *
     * @return array{version: string|null, version_source: string|null, error: string|null}
     */
    private function readVersion(string $sPath): array
    {
        if (array_key_exists($sPath, $this->aVersionCache)) {
            return $this->aVersionCache[$sPath];
        }

        return $this->aVersionCache[$sPath] = $this->resolveVersion($sPath);
    }

    // --------------------------------------------------------------------------

    /**
     * @param string $sPath The binary to inspect
     *
     * @return array{version: string|null, version_source: string|null, error: string|null}
     */
    private function resolveVersion(string $sPath): array
    {
        $sFromPath = $this->getManager($sPath)['version'] ?? null;

        if (!is_file($sPath) || !is_executable($sPath)) {
            return $this->versionFromPath($sFromPath, $sPath . ' is not an executable file');
        }

        if (!$this->isTrusted($sPath)) {
            return $this->versionFromPath(
                $sFromPath,
                'Not executed: ' . $sPath . ' is not owned exclusively by root'
            );
        }

        try {

            $sVersion = trim(System::execString($this->buildVersionCommand($sPath)));

        } catch (CommandFailedException $e) {
            return $this->versionFromPath($sFromPath, 'Failed to read version: ' . $e->getMessage());
        }

        if (!preg_match(self::VERSION, $sVersion)) {
            return $this->versionFromPath($sFromPath, 'Unrecognised version output from ' . $sPath);
        }

        return [
            'version'        => 'v' . ltrim($sVersion, 'v'),
            'version_source' => self::VERSION_SOURCE_BINARY,
            'error'          => null,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Falls back to the version the path states, where the binary could not be
     * asked for its own
     *
     * The reason it could not be asked is only reported where the fallback found
     * nothing either; a version manager install carries no fault, and saying so
     * on every one of them would bury the cases which do.
     *
     * @param string|null $sVersion The version taken from the path, if any
     * @param string      $sError   Why the binary was not executed
     *
     * @return array{version: string|null, version_source: string|null, error: string|null}
     */
    private function versionFromPath(?string $sVersion, string $sError): array
    {
        if ($sVersion === null) {
            return $this->versionError($sError);
        }

        return [
            'version'        => $sVersion,
            'version_source' => self::VERSION_SOURCE_PATH,
            'error'          => null,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * A version which could not be established, and why
     *
     * @param string $sError The reason
     *
     * @return array{version: string|null, version_source: string|null, error: string|null}
     */
    private function versionError(string $sError): array
    {
        return [
            'version'        => null,
            'version_source' => null,
            'error'          => $sError,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Builds the command which asks a node binary for its version
     *
     * The environment is emptied so that a NODE_OPTIONS inherited from wherever
     * the heartbeat was invoked cannot alter what runs, and the call is bounded
     * so that a wedged binary cannot stall the heartbeat.
     *
     * @param string $sPath The binary to interrogate
     *
     * @return string
     */
    private function buildVersionCommand(string $sPath): string
    {
        $sCommand = escapeshellarg($sPath) . ' --version 2>/dev/null';

        if (System::commandExists('env')) {
            $sCommand = 'env -i ' . $sCommand;
        }

        if (System::commandExists('timeout')) {
            $sCommand = 'timeout ' . self::EXEC_TIMEOUT . ' ' . $sCommand;
        }

        return $sCommand;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a binary can be executed as root without handing
     * control to whoever owns the path to it
     *
     * Both the path as given and the path it resolves to are checked, since a
     * symlink is only as trustworthy as its target.
     *
     * @param string $sPath The path to check
     *
     * @return bool
     */
    private function isTrusted(string $sPath): bool
    {
        $sReal = realpath($sPath);

        if ($sReal === false) {
            return false;
        }

        return $this->isRootOwnedPath($sPath) && ($sReal === $sPath || $this->isRootOwnedPath($sReal));
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether root owns every component of a path, and whether any
     * of them can be written to by anybody else
     *
     * A directory anyone can write to is enough on its own: the binary at the
     * end of the path can be swapped for another without touching the binary
     * itself.
     *
     * @param string $sPath The path to check
     *
     * @return bool
     */
    private function isRootOwnedPath(string $sPath): bool
    {
        if (!str_starts_with($sPath, '/')) {
            return false;
        }

        $sCurrent   = '';
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

            //  Group or other writable
            if ($iPerms & 0022) {
                return false;
            }
        }

        return true;
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
