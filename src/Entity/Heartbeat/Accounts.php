<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Helper\Redact;
use Shed\Cli\Helper\System;

/**
 * Class Accounts
 *
 * Reports the accounts on the host and the SSH keys each one authorises: every
 * entry in the user database, its group membership and password state, and the
 * type, size, SHA256 fingerprint, comment and options of every key in the files
 * sshd reads for that account. sshd's own effective settings for key and
 * password login are reported alongside, since they decide which of those keys
 * actually let anyone in.
 *
 * **Key material never leaves the server** — only its fingerprint, the same
 * `SHA256:…` string `ssh-keygen -lf` prints, which is enough to find the same
 * key on every other host without being usable as one.
 *
 * Mirrors Sudoers' approach throughout: a permissions failure is reported as an
 * explicit `error`, never as an absence of keys, and a line this collector
 * cannot make sense of is counted rather than guessed at.
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Accounts implements \JsonSerializable
{
    /**
     * Holds the password hashes. Mode 0640 root:shadow, so reading it requires
     * root; only whether each account has a usable password is reported, never
     * the hash.
     *
     * @var string
     */
    private const SHADOW = '/etc/shadow';

    /**
     * The OpenSSH server, whose effective configuration (`sshd -T`) says which
     * files keys are read from.
     *
     * @var string
     */
    private const SSHD = '/usr/sbin/sshd';

    /**
     * sshd's own default AuthorizedKeysFile, used when its configuration cannot
     * be read so the keys are still found where they almost always are.
     *
     * @var string[]
     */
    private const DEFAULT_AUTHORIZED_KEYS = ['.ssh/authorized_keys', '.ssh/authorized_keys2'];

    /**
     * Shells which refuse an interactive login. An account with one of these
     * can still be the target of a key with a forced `command=`, so its keys are
     * reported regardless.
     *
     * @var string[]
     */
    private const NO_LOGIN_SHELLS = ['nologin', 'false', 'sync', 'shutdown', 'halt'];

    /**
     * The key types sshd accepts in an authorized_keys file, including
     * certificates and FIDO (`sk-`) keys.
     *
     * @var string
     */
    private const KEY_TYPE = '/^(?:ssh-[a-z0-9]+|ecdsa-sha2-nistp\d+|sk-[a-z0-9-]+@openssh\.com|[a-z0-9-]+-cert-v01@openssh\.com)$/';

    /**
     * The sshd settings reported, as `sshd -T` names them (lower case).
     *
     * @var string[]
     */
    private const SSHD_SETTINGS = [
        'permitrootlogin',
        'pubkeyauthentication',
        'passwordauthentication',
        'kbdinteractiveauthentication',
        'authorizedkeyscommand',
        'authorizedkeyscommanduser',
    ];

    /**
     * Settings `sshd -T` may print more than once, one value list per line.
     *
     * @var string[]
     */
    private const SSHD_LISTS = ['allowusers', 'allowgroups', 'denyusers', 'denygroups'];

    /**
     * The most accounts reported. A host enumerating a large directory service
     * could otherwise list tens of thousands; accounts are taken in uid order,
     * and `truncated` says when the rest were left out.
     *
     * @var int
     */
    private const MAX_USERS = 2000;

    /**
     * The most keys reported from one file.
     *
     * @var int
     */
    private const MAX_KEYS = 1000;

    /**
     * How much of one authorized_keys file is read.
     *
     * @var int
     */
    private const MAX_FILE_BYTES = 1048576;

    // --------------------------------------------------------------------------

    /**
     * Gathers the accounts on the host and the SSH keys each one authorises
     *
     * @return array|null
     */
    public function get(): ?array
    {
        switch (Os::getType()) {
            case Os::LINUX:
                return $this->collect();

            case Os::MACOS:
                return null; // not a deploy target; a laptop's accounts are not what's being audited here

            default:
                throw new HeartbeatException('Unable to determine account status.');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * @return array
     */
    private function collect(): array
    {
        $aSshd = $this->getSshd();

        $aShadow      = $this->getShadow();
        $sShadowError = $aShadow === null
            ? 'Access to ' . self::SHADOW . ' denied - insufficient permissions (root required)'
            : null;

        $aPasswd = $this->getPasswd();
        if ($aPasswd === null) {
            return [
                'users'        => (object) [],
                'sshd'         => $aSshd,
                'shadow_error' => $sShadowError,
                'truncated'    => false,
                'error'        => 'Failed to read the user database (getent passwd)',
            ];
        }

        $bTruncated = count($aPasswd) > self::MAX_USERS;
        $aPasswd    = array_slice($aPasswd, 0, self::MAX_USERS, true);

        $aGroups   = $this->getGroups();
        $aPatterns = $aSshd['authorized_keys_files'] ?? self::DEFAULT_AUTHORIZED_KEYS;
        $aUsers    = [];

        foreach ($aPasswd as $sName => $aUser) {
            $aUsers[$sName] = $this->getUser($aUser, $aGroups, $aShadow, $aPatterns);
        }

        return [
            'users'        => (object) $aUsers,
            'sshd'         => $aSshd,
            'shadow_error' => $sShadowError,
            'truncated'    => $bTruncated,
            'error'        => null,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Builds one account's entry
     *
     * @param array      $aUser     The passwd entry
     * @param array      $aGroups   gid => name, and user => supplementary groups
     * @param array|null $aShadow   name => shadow entry, or null when unreadable
     * @param string[]   $aPatterns AuthorizedKeysFile patterns
     *
     * @return array
     */
    private function getUser(array $aUser, array $aGroups, ?array $aShadow, array $aPatterns): array
    {
        $sName  = $aUser['name'];
        $aNames = [];

        if (isset($aGroups['by_gid'][$aUser['gid']])) {
            $aNames[] = $aGroups['by_gid'][$aUser['gid']];
        }

        foreach ($aGroups['by_member'][$sName] ?? [] as $sGroup) {
            if (!in_array($sGroup, $aNames, true)) {
                $aNames[] = $sGroup;
            }
        }

        [$aKeyFiles, $sError] = $this->getAuthorizedKeys($aUser, $aPatterns);

        return [
            'uid'             => $aUser['uid'],
            'gid'             => $aUser['gid'],
            'groups'          => $aNames,
            'gecos'           => $aUser['gecos'] !== '' ? $aUser['gecos'] : null,
            'home'            => $aUser['home'],
            'shell'           => $aUser['shell'] !== '' ? $aUser['shell'] : null,
            'login_shell'     => $this->isLoginShell($aUser['shell']),
            'password'        => $aShadow === null ? null : $this->passwordState($aShadow[$sName] ?? null),
            'expires'         => $aShadow === null ? null : $this->expiry($aShadow[$sName] ?? null),
            'authorized_keys' => (object) $aKeyFiles,
            'error'           => $sError,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Reads the user database
     *
     * `getent` is preferred over reading /etc/passwd so that users provided by
     * a directory service are seen too; the file is the fallback. Sorted by uid
     * so that, if the list has to be truncated, root and the local accounts
     * survive it.
     *
     * @return array<string, array>|null Keyed by name; null when it could not be read
     */
    private function getPasswd(): ?array
    {
        $aOutput = [];

        try {

            System::exec('getent passwd 2>/dev/null || cat /etc/passwd', $aOutput);

        } catch (CommandFailedException) {
            return null;
        }

        $aUsers = [];

        foreach ($aOutput as $sLine) {

            //  name:password:uid:gid:gecos:home:shell
            $aParts = explode(':', $sLine);

            if (count($aParts) < 7 || $aParts[0] === '' || !ctype_digit($aParts[2]) || !ctype_digit($aParts[3])) {
                continue;
            }

            //  A name may appear twice where a directory service shadows a local
            //  account; the first is the one the system resolves
            if (isset($aUsers[$aParts[0]])) {
                continue;
            }

            $aUsers[$aParts[0]] = [
                'name'  => $aParts[0],
                'uid'   => (int) $aParts[2],
                'gid'   => (int) $aParts[3],
                'gecos' => $aParts[4],
                'home'  => $aParts[5],
                'shell' => $aParts[6],
            ];
        }

        uasort($aUsers, static fn (array $a, array $b) => $a['uid'] <=> $b['uid']);

        return $aUsers;
    }

    // --------------------------------------------------------------------------

    /**
     * Reads the group database once, both ways round
     *
     * @return array{by_gid: array<int, string>, by_member: array<string, string[]>}
     */
    private function getGroups(): array
    {
        $aGroups = ['by_gid' => [], 'by_member' => []];
        $aOutput = [];

        try {

            System::exec('getent group 2>/dev/null || cat /etc/group', $aOutput);

        } catch (CommandFailedException) {
            return $aGroups;
        }

        foreach ($aOutput as $sLine) {

            //  name:password:gid:member,list
            $aParts = explode(':', $sLine, 4);

            if (count($aParts) < 4 || $aParts[0] === '' || !ctype_digit($aParts[2])) {
                continue;
            }

            $aGroups['by_gid'][(int) $aParts[2]] ??= $aParts[0];

            foreach (array_filter(explode(',', trim($aParts[3]))) as $sMember) {
                $aGroups['by_member'][$sMember][] = $aParts[0];
            }
        }

        return $aGroups;
    }

    // --------------------------------------------------------------------------

    /**
     * Reads /etc/shadow
     *
     * Only the fields this needs are kept; the hash itself is reduced to a
     * state by passwordState() and goes no further.
     *
     * @return array<string, array{hash: string, expire: string}>|null Null when unreadable
     */
    private function getShadow(): ?array
    {
        if (!is_readable(self::SHADOW)) {
            return null;
        }

        $sContents = @file_get_contents(self::SHADOW);
        if ($sContents === false) {
            return null;
        }

        $aShadow = [];

        foreach (preg_split('/\r?\n/', $sContents) ?: [] as $sLine) {

            //  name:hash:lastchg:min:max:warn:inactive:expire:reserved
            $aParts = explode(':', $sLine);

            if (count($aParts) < 8 || $aParts[0] === '') {
                continue;
            }

            $aShadow[$aParts[0]] = [
                'hash'   => $aParts[1],
                'expire' => $aParts[7],
            ];
        }

        return $aShadow;
    }

    // --------------------------------------------------------------------------

    /**
     * Reduces a shadow entry's hash to whether the account has a usable password
     *
     * `locked` covers every form that refuses a password login (`!`, `!!`, `*`,
     * `!hash`) — a locked password does **not** stop key-based login, which is
     * the point of reporting the keys next to it. `none` is an empty hash: a
     * passwordless account, which sshd only lets in with PermitEmptyPasswords.
     *
     * @param array|null $aEntry The shadow entry
     *
     * @return string|null `set`, `locked` or `none`; null when the account has no shadow entry
     */
    private function passwordState(?array $aEntry): ?string
    {
        if ($aEntry === null) {
            return null;
        }

        $sHash = $aEntry['hash'];

        if ($sHash === '') {
            return 'none';
        }

        return str_starts_with($sHash, '!') || str_starts_with($sHash, '*') ? 'locked' : 'set';
    }

    // --------------------------------------------------------------------------

    /**
     * The date an account expires on, from shadow's days-since-epoch field
     *
     * @param array|null $aEntry The shadow entry
     *
     * @return string|null Y-m-d, or null when the account never expires
     */
    private function expiry(?array $aEntry): ?string
    {
        $sExpire = $aEntry['expire'] ?? '';

        if (!ctype_digit($sExpire)) {
            return null;
        }

        return gmdate('Y-m-d', (int) $sExpire * 86400);
    }

    // --------------------------------------------------------------------------

    /**
     * @param string $sShell The account's shell
     *
     * @return bool
     */
    private function isLoginShell(string $sShell): bool
    {
        return $sShell !== '' && !in_array(basename($sShell), self::NO_LOGIN_SHELLS, true);
    }

    // --------------------------------------------------------------------------

    /**
     * Reads sshd's effective configuration
     *
     * `sshd -T` prints every setting after defaults and includes have been
     * applied, so nothing here has to parse sshd_config's own grammar. It is
     * evaluated without a connection, so a `Match` block's overrides are not
     * reflected — the settings reported are the global ones.
     *
     * @return array
     */
    private function getSshd(): array
    {
        $aSshd = [
            'present'                        => is_file(self::SSHD),
            'permit_root_login'              => null,
            'pubkey_authentication'          => null,
            'password_authentication'        => null,
            'kbd_interactive_authentication' => null,
            'authorized_keys_files'          => null,
            'authorized_keys_command'        => null,
            'authorized_keys_command_user'   => null,
            'allow_users'                    => [],
            'allow_groups'                   => [],
            'deny_users'                     => [],
            'deny_groups'                    => [],
            'error'                          => null,
        ];

        if (!$aSshd['present']) {
            return $aSshd;
        }

        $aOutput = [];

        try {

            System::exec(escapeshellarg(self::SSHD) . ' -T 2>/dev/null', $aOutput);

        } catch (CommandFailedException) {
            $aSshd['error'] = 'Failed to read the effective sshd configuration (sshd -T, root required)';

            return $aSshd;
        }

        return array_merge($aSshd, $this->parseSshdConfig(implode("\n", $aOutput)));
    }

    // --------------------------------------------------------------------------

    /**
     * Pulls the reported settings out of `sshd -T` output
     *
     * @param string $sOutput The output of `sshd -T`
     *
     * @return array
     */
    public function parseSshdConfig(string $sOutput): array
    {
        $aSshd = [];

        foreach (preg_split('/\r?\n/', $sOutput) ?: [] as $sLine) {

            $aParts = preg_split('/\s+/', trim($sLine), 2);

            if (count($aParts) < 2) {
                continue;
            }

            [$sSetting, $sValue] = [strtolower($aParts[0]), trim($aParts[1])];

            if ($sSetting === 'authorizedkeysfile') {
                $aSshd['authorized_keys_files'] = $sValue === 'none'
                    ? []
                    : array_values(array_filter(preg_split('/\s+/', $sValue) ?: []));

            } elseif (in_array($sSetting, self::SSHD_SETTINGS, true)) {
                $aSshd[$this->sshdKey($sSetting)] = $sValue === 'none' ? null : $sValue;

            } elseif (in_array($sSetting, self::SSHD_LISTS, true)) {
                $sKey = $this->sshdKey($sSetting);
                $aSshd[$sKey] = array_merge($aSshd[$sKey] ?? [], array_filter(preg_split('/\s+/', $sValue) ?: []));
            }
        }

        return $aSshd;
    }

    // --------------------------------------------------------------------------

    /**
     * The payload key for an sshd setting
     *
     * @param string $sSetting The setting, as `sshd -T` names it
     *
     * @return string
     */
    private function sshdKey(string $sSetting): string
    {
        return match ($sSetting) {
            'permitrootlogin'              => 'permit_root_login',
            'pubkeyauthentication'         => 'pubkey_authentication',
            'passwordauthentication'       => 'password_authentication',
            'kbdinteractiveauthentication' => 'kbd_interactive_authentication',
            'authorizedkeyscommand'        => 'authorized_keys_command',
            'authorizedkeyscommanduser'    => 'authorized_keys_command_user',
            'allowusers'                   => 'allow_users',
            'allowgroups'                  => 'allow_groups',
            'denyusers'                    => 'deny_users',
            'denygroups'                   => 'deny_groups',
        };
    }

    // --------------------------------------------------------------------------

    /**
     * Reads every authorized_keys file sshd would read for one account
     *
     * A file which does not exist is left out — nothing is authorised by it. A
     * file which exists but could not be read, or which this declines to read,
     * is reported with an `error` in place of its keys.
     *
     * @param array    $aUser     The passwd entry
     * @param string[] $aPatterns AuthorizedKeysFile patterns
     *
     * @return array{0: array<string, array>, 1: string|null} The files, and an account-level error
     */
    private function getAuthorizedKeys(array $aUser, array $aPatterns): array
    {
        $sHome      = $aUser['home'];
        $bHomeKnown = $sHome !== '' && is_dir($sHome);
        $sError     = null;

        //  Without root, another user's home cannot be searched, and every file
        //  in it would look absent — which must not read as "no keys"
        if ($bHomeKnown && !is_executable($sHome)) {
            $sError     = 'Access to ' . $sHome . ' denied - insufficient permissions (root required)';
            $bHomeKnown = false;
        }

        $aFiles = [];

        foreach ($aPatterns as $sPattern) {

            $sPath = $this->expandPattern($sPattern, $aUser);
            $bInHome = !str_starts_with($sPath, '/');

            if ($bInHome) {
                if (!$bHomeKnown) {
                    continue;
                }
                $sPath = rtrim($sHome, '/') . '/' . $sPath;
            }

            if (isset($aFiles[$sPath])) {
                continue;
            }

            if (!file_exists($sPath)) {
                //  ~/.ssh is normally 0700: without root, its files look absent
                $sDir = dirname($sPath);
                if (is_dir($sDir) && !is_executable($sDir)) {
                    $aFiles[$sPath] = $this->fileError('Access to ' . $sDir . ' denied - insufficient permissions (root required)');
                }
                continue;
            }

            $aFiles[$sPath] = $this->readAuthorizedKeys($sPath, $aUser, $bInHome);
        }

        return [$aFiles, $sError];
    }

    // --------------------------------------------------------------------------

    /**
     * Expands sshd's AuthorizedKeysFile tokens for one account
     *
     * @param string $sPattern The pattern, e.g. `.ssh/authorized_keys` or `/etc/ssh/keys/%u`
     * @param array  $aUser    The passwd entry
     *
     * @return string
     */
    private function expandPattern(string $sPattern, array $aUser): string
    {
        return preg_replace_callback(
            '/%([%hUu])/',
            static fn (array $aMatch) => match ($aMatch[1]) {
                '%' => '%',
                'h' => $aUser['home'],
                'U' => (string) $aUser['uid'],
                'u' => $aUser['name'],
            },
            $sPattern
        ) ?? $sPattern;
    }

    // --------------------------------------------------------------------------

    /**
     * Reads and parses one authorized_keys file
     *
     * The heartbeat runs as root, so a symlink planted in a home directory
     * would otherwise be followed into anything on the host. The same guard
     * Node applies to files it reads from a home is applied here: a file found
     * through the home directory must resolve to somewhere still inside it, and
     * any file must belong to that user or to root — which is also what sshd's
     * StrictModes demands before it will use the file at all.
     *
     * @param string $sPath   The file
     * @param array  $aUser   The account it belongs to
     * @param bool   $bInHome Whether it was reached through the account's home
     *
     * @return array{keys: array, invalid: int, truncated: bool, error: ?string}
     */
    private function readAuthorizedKeys(string $sPath, array $aUser, bool $bInHome): array
    {
        $sReal = realpath($sPath);

        if ($sReal === false || !is_file($sReal)) {
            return $this->fileError('Not read: ' . $sPath . ' is not a regular file');
        }

        if ($bInHome) {
            $sHome = realpath($aUser['home']);
            if ($sHome === false || !str_starts_with($sReal, rtrim($sHome, '/') . '/')) {
                return $this->fileError('Not read: ' . $sPath . ' resolves outside ' . $aUser['home']);
            }
        }

        $iOwner = @fileowner($sReal);
        if ($iOwner === false || ($iOwner !== $aUser['uid'] && $iOwner !== 0)) {
            return $this->fileError('Not read: ' . $sPath . ' is owned by neither ' . $aUser['name'] . ' nor root, so sshd would refuse it too');
        }

        if (!is_readable($sReal)) {
            return $this->fileError('Access to ' . $sPath . ' denied - insufficient permissions (root required)');
        }

        $sContents = @file_get_contents($sReal, false, null, 0, self::MAX_FILE_BYTES);
        if ($sContents === false) {
            return $this->fileError('Failed to read ' . $sPath);
        }

        return $this->parseAuthorizedKeys($sContents) + ['error' => null];
    }

    // --------------------------------------------------------------------------

    /**
     * @param string $sError Why the file's keys are missing
     *
     * @return array{keys: array, invalid: int, truncated: bool, error: string}
     */
    private function fileError(string $sError): array
    {
        return [
            'keys'      => [],
            'invalid'   => 0,
            'truncated' => false,
            'error'     => $sError,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Parses the keys out of an authorized_keys file's contents
     *
     * Each line is `[options] type base64 [comment]`. A line which is neither
     * blank, a comment, nor a key whose encoded type matches its declared one is
     * counted in `invalid` rather than skipped silently, so a file full of
     * damaged lines is not mistaken for an empty one.
     *
     * @param string $sContents The file's contents
     *
     * @return array{keys: array, invalid: int, truncated: bool}
     */
    public function parseAuthorizedKeys(string $sContents): array
    {
        $aKeys      = [];
        $iInvalid   = 0;
        $bTruncated = false;

        foreach (preg_split('/\r?\n/', $sContents) ?: [] as $sLine) {

            $sLine = trim($sLine);

            if ($sLine === '' || str_starts_with($sLine, '#')) {
                continue;
            }

            $aKey = $this->parseKeyLine($sLine);

            if ($aKey === null) {
                $iInvalid++;
                continue;
            }

            if (count($aKeys) >= self::MAX_KEYS) {
                $bTruncated = true;
                break;
            }

            $aKeys[] = $aKey;
        }

        return [
            'keys'      => $aKeys,
            'invalid'   => $iInvalid,
            'truncated' => $bTruncated,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Parses one authorized_keys line
     *
     * @param string $sLine The line, already trimmed
     *
     * @return array|null Null when the line is not a key
     */
    private function parseKeyLine(string $sLine): ?array
    {
        $aOptions = [];
        $aTokens  = preg_split('/\s+/', $sLine, 2);

        //  No options: the line starts with the key type itself
        if (!preg_match(self::KEY_TYPE, $aTokens[0])) {
            [$aOptions, $sLine] = $this->splitOptions($sLine);
            if ($sLine === null) {
                return null;
            }
        }

        $aParts = preg_split('/\s+/', $sLine, 3);
        if (count($aParts) < 2 || !preg_match(self::KEY_TYPE, $aParts[0])) {
            return null;
        }

        [$sType, $sEncoded] = $aParts;
        $sComment = trim($aParts[2] ?? '');

        $sBlob   = base64_decode($sEncoded, true);
        $iOffset = 0;
        if ($sBlob === false || $this->readString($sBlob, $iOffset) !== $sType) {
            return null;
        }

        return [
            'type'        => $sType,
            'bits'        => $this->bits($sType, $sBlob),
            'fingerprint' => 'SHA256:' . rtrim(base64_encode(hash('sha256', $sBlob, true)), '='),
            'comment'     => $sComment !== '' ? Redact::secrets($sComment) : null,
            'options'     => array_map(static fn (string $sOption) => Redact::secrets($sOption), $aOptions),
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Splits a line's leading options from the key that follows them
     *
     * Options are comma separated and end at the first space outside a quoted
     * value — `command="a, b"` is one option, its comma and space included.
     *
     * @param string $sLine The line
     *
     * @return array{0: string[], 1: string|null} The options, and the rest of the line
     */
    private function splitOptions(string $sLine): array
    {
        $aOptions = [];
        $sCurrent = '';
        $bQuoted  = false;
        $iLength  = strlen($sLine);

        for ($i = 0; $i < $iLength; $i++) {

            $sChar = $sLine[$i];

            if ($bQuoted && $sChar === '\\' && ($sLine[$i + 1] ?? '') === '"') {
                $sCurrent .= '\\"';
                $i++;
                continue;
            }

            if ($sChar === '"') {
                $bQuoted = !$bQuoted;
            }

            if (!$bQuoted && $sChar === ',') {
                $aOptions[] = $sCurrent;
                $sCurrent   = '';
                continue;
            }

            if (!$bQuoted && ($sChar === ' ' || $sChar === "\t")) {
                $aOptions[] = $sCurrent;

                return [array_values(array_filter($aOptions, 'strlen')), ltrim(substr($sLine, $i))];
            }

            $sCurrent .= $sChar;
        }

        return [[], null];
    }

    // --------------------------------------------------------------------------

    /**
     * The key's size in bits, read from its public blob
     *
     * @param string $sType The key type
     * @param string $sBlob The decoded public key
     *
     * @return int|null Null for a type whose size is not read here (certificates)
     */
    private function bits(string $sType, string $sBlob): ?int
    {
        return match (true) {
            $sType === 'ssh-rsa' => $this->mpintBits($sBlob, 2),
            $sType === 'ssh-dss' => $this->mpintBits($sBlob, 1),
            $sType === 'ssh-ed25519', $sType === 'sk-ssh-ed25519@openssh.com' => 256,
            $sType === 'ssh-ed448' => 456,
            $sType === 'sk-ecdsa-sha2-nistp256@openssh.com' => 256,
            (bool) preg_match('/^ecdsa-sha2-nistp(\d+)$/', $sType, $aMatch) => (int) $aMatch[1],
            default => null,
        };
    }

    // --------------------------------------------------------------------------

    /**
     * The bit length of one of the blob's mpint fields
     *
     * @param string $sBlob  The decoded public key
     * @param int    $iField Which field after the type (1-based): RSA is `e, n`, DSA is `p, q, g, y`
     *
     * @return int|null
     */
    private function mpintBits(string $sBlob, int $iField): ?int
    {
        $iOffset = 0;
        $this->readString($sBlob, $iOffset);

        $sValue = null;
        for ($i = 0; $i < $iField; $i++) {
            $sValue = $this->readString($sBlob, $iOffset);
            if ($sValue === null) {
                return null;
            }
        }

        $sValue = ltrim((string) $sValue, "\0");
        if ($sValue === '') {
            return null;
        }

        return (strlen($sValue) - 1) * 8 + strlen(decbin(ord($sValue[0])));
    }

    // --------------------------------------------------------------------------

    /**
     * Reads one length-prefixed string from an SSH wire-format blob
     *
     * @param string   $sBlob   The blob
     * @param int|null $iOffset Where to read from; advanced past what was read
     *
     * @return string|null
     */
    private function readString(string $sBlob, ?int &$iOffset): ?string
    {
        $iOffset ??= 0;

        if (strlen($sBlob) < $iOffset + 4) {
            return null;
        }

        $aLength = unpack('N', substr($sBlob, $iOffset, 4));
        $iLength = $aLength[1] ?? null;
        if (!is_int($iLength) || strlen($sBlob) < $iOffset + 4 + $iLength) {
            return null;
        }

        $sValue   = substr($sBlob, $iOffset + 4, $iLength);
        $iOffset += 4 + $iLength;

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
