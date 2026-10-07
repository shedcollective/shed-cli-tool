<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Helper\System;

/**
 * Class Mysql
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Mysql implements \JsonSerializable
{
    /**
     * How long a wedged mysqld --version may stall the heartbeat
     *
     * @var int
     */
    private const EXEC_TIMEOUT = 5;

    /**
     * Server binaries to look for, in preference order. Ubuntu's MariaDB still
     * ships a `mysqld` symlink, so the engine is taken from the version banner
     * rather than from the filename.
     *
     * @var array<int, string>
     */
    private const CANDIDATES = [
        '/usr/sbin/mariadbd',
        '/usr/sbin/mysqld',
        '/usr/bin/mariadbd',
        '/usr/bin/mysqld',
    ];

    /**
     * dpkg package names that mean a MySQL or MariaDB server is installed.
     * `mysql-server-core-*` is the server binary without the metapackage.
     *
     * @var array<int, string>
     */
    private const PACKAGES = [
        'mariadb-server',
        'mysql-server',
        'mysql-server-core-8.4',
        'mysql-server-core-8.0',
        'mysql-server-core-5.7',
    ];

    /**
     * Gathers the MySQL / MariaDB server version, if one is installed
     *
     * A missing database is reported as `present: false` — it must never throw,
     * because that would abort the whole heartbeat.
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
                throw new HeartbeatException('Unable to determine MySQL status.');
        }
    }

    /**
     * Parses a mysqld / mariadbd `--version` banner into engine + version
     *
     * The engine comes from the banner text (`MariaDB` vs not), not the binary
     * name — Ubuntu's MariaDB still ships a `mysqld` symlink.
     *
     * @param string $sBanner The first line of `--version` output
     *
     * @return array{engine: string, version: string, version_full: string}|null
     */
    public static function parseBanner(string $sBanner): ?array
    {
        if (!preg_match('/\bVer\s+(\S+)/i', $sBanner, $aMatch)) {
            return null;
        }

        $sFull = $aMatch[1];

        if (!preg_match('/^(\d+\.\d+\.\d+)/', $sFull, $aVersion)) {
            return null;
        }

        return [
            'engine'       => stripos($sBanner, 'MariaDB') !== false ? 'mariadb' : 'mysql',
            'version'      => $aVersion[1],
            'version_full' => $sFull,
        ];
    }

    /**
     * Strips a Debian epoch and takes the upstream x.y.z from a dpkg version
     *
     * @param string $sRaw The Version field from dpkg-query
     *
     * @return array{version: string, version_full: string}|null
     */
    public static function parsePackageVersion(string $sRaw): ?array
    {
        $sFull = preg_replace('/^\d+:/', '', $sRaw) ?? $sRaw;

        if (!preg_match('/^(\d+\.\d+\.\d+)/', $sFull, $aMatch)) {
            return null;
        }

        return [
            'version'      => $aMatch[1],
            'version_full' => $sFull,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collect(): array
    {
        $sBinary     = $this->findBinary();
        $aFromBinary = $sBinary !== null ? $this->versionFromBinary($sBinary) : $this->unreadable('No MySQL or MariaDB server binary found');

        if ($aFromBinary['version'] !== null) {
            return $this->reported(true, $aFromBinary);
        }

        $aFromPackage = $this->versionFromPackage();

        if ($aFromPackage['version'] !== null) {
            return $this->reported(true, $aFromPackage);
        }

        if ($sBinary !== null) {
            return $this->reported(true, $aFromBinary);
        }

        return $this->reported(false, $this->unreadable(null));
    }

    /**
     * @return array<string, mixed>
     */
    private function reported(bool $bPresent, array $aVersion): array
    {
        return [
            'present'        => $bPresent,
            'engine'         => $aVersion['engine'],
            'version'        => $aVersion['version'],
            'version_full'   => $aVersion['version_full'],
            'version_source' => $aVersion['version_source'],
            'error'          => $bPresent ? $aVersion['error'] : null,
        ];
    }

    private function findBinary(): ?string
    {
        foreach (self::CANDIDATES as $sPath) {
            if (is_file($sPath) && is_executable($sPath)) {
                return $sPath;
            }
        }

        return null;
    }

    /**
     * @return array{engine: string|null, version: string|null, version_full: string|null, version_source: string|null, error: string|null}
     */
    private function versionFromBinary(string $sPath): array
    {
        if (!$this->isTrusted($sPath)) {
            return $this->unreadable('Not executed: ' . $sPath . ' is not owned exclusively by root');
        }

        try {
            $sBanner = trim(System::execString($this->buildVersionCommand($sPath)));
        } catch (CommandFailedException $e) {
            return $this->unreadable('Failed to read version: ' . $e->getMessage());
        }

        $aParsed = self::parseBanner($sBanner);

        if ($aParsed === null) {
            return $this->unreadable('Unrecognised version output from ' . $sPath);
        }

        return [
            'engine'         => $aParsed['engine'],
            'version'        => $aParsed['version'],
            'version_full'   => $aParsed['version_full'],
            'version_source' => 'binary',
            'error'          => null,
        ];
    }

    /**
     * @return array{engine: string|null, version: string|null, version_full: string|null, version_source: string|null, error: string|null}
     */
    private function versionFromPackage(): array
    {
        if (!System::commandExists('dpkg-query')) {
            return $this->unreadable('dpkg-query is not available');
        }

        foreach (self::PACKAGES as $sPackage) {
            $aInstalled = $this->queryPackage($sPackage);

            if ($aInstalled === null) {
                continue;
            }

            $aParsed = self::parsePackageVersion($aInstalled['version']);

            if ($aParsed === null) {
                continue;
            }

            return [
                'engine'         => str_starts_with($aInstalled['name'], 'mariadb') ? 'mariadb' : 'mysql',
                'version'        => $aParsed['version'],
                'version_full'   => $aParsed['version_full'],
                'version_source' => 'package',
                'error'          => null,
            ];
        }

        return $this->unreadable('No mysql-server or mariadb-server package is installed');
    }

    /**
     * @return array{name: string, version: string}|null
     */
    private function queryPackage(string $sPackage): ?array
    {
        try {
            $sLine = System::execString(
                'dpkg-query -W -f=\'${Package}\t${Status}\t${Version}\' ' . escapeshellarg($sPackage) . ' 2>/dev/null'
            );
        } catch (CommandFailedException) {
            return null;
        }

        $aParts = explode("\t", $sLine);

        if (count($aParts) < 3 || !str_contains($aParts[1], 'install ok installed')) {
            return null;
        }

        return [
            'name'    => $aParts[0],
            'version' => $aParts[2],
        ];
    }

    /**
     * The environment is emptied so inherited MYSQL_* variables cannot alter
     * what runs, and the call is bounded so a wedged binary cannot stall the beat.
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
     * @return array{engine: null, version: null, version_full: null, version_source: null, error: string|null}
     */
    private function unreadable(?string $sError): array
    {
        return [
            'engine'         => null,
            'version'        => null,
            'version_full'   => null,
            'version_source' => null,
            'error'          => $sError,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
}
