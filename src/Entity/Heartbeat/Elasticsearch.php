<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Helper\System;

/**
 * Class Elasticsearch
 *
 * Reports whether an Elasticsearch server is installed on the host, its version, and whether its
 * systemd unit is running.
 *
 * Nothing here talks to the node or executes it. Elasticsearch 8 requires authentication on its
 * HTTP port by default, and starting its launcher spins up a JVM, so the version comes from dpkg
 * and, failing that, from the versioned jar the install ships.
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Elasticsearch implements \JsonSerializable
{
    /**
     * Where Elastic's Debian package installs the server
     *
     * @var string
     */
    private const HOME = '/usr/share/elasticsearch';

    /**
     * The dpkg package Elastic's apt repository ships the server as
     *
     * @var string
     */
    private const PACKAGE = 'elasticsearch';

    /**
     * Gathers the Elasticsearch version and unit state, if Elasticsearch is installed
     *
     * A missing Elasticsearch is reported as `present: false` — it must never throw, because that
     * would abort the whole heartbeat.
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
                throw new HeartbeatException('Unable to determine Elasticsearch status.');
        }
    }

    /**
     * Takes the upstream x.y.z from a dpkg version, e.g. `8.15.0` or `7.17.24`
     *
     * @param string $sRaw The Version field from dpkg-query
     *
     * @return array{version: string, version_full: string}|null
     */
    public static function parsePackageVersion(string $sRaw): ?array
    {
        $sFull = preg_replace('/^\d+:/', '', trim($sRaw)) ?? $sRaw;

        if (!preg_match('/^(\d+\.\d+\.\d+)/', $sFull, $aMatch)) {
            return null;
        }

        return [
            'version'      => $aMatch[1],
            'version_full' => $sFull,
        ];
    }

    /**
     * Takes the version from the server jar's filename, e.g. `elasticsearch-8.15.0.jar`
     *
     * Only the server's own jar matches — `elasticsearch-core-8.15.0.jar` and its siblings are
     * skipped.
     *
     * @param array<int, string> $aFilenames Basenames from the install's `lib` directory
     *
     * @return array{version: string, version_full: string}|null
     */
    public static function parseJarVersion(array $aFilenames): ?array
    {
        foreach ($aFilenames as $sFilename) {
            if (preg_match('/^elasticsearch-(\d+\.\d+\.\d+(?:-[A-Za-z0-9.]+)?)\.jar$/', $sFilename, $aMatch)) {
                return self::parsePackageVersion($aMatch[1]);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function collect(): array
    {
        $aPackage   = $this->findPackage();
        $bInstalled = $aPackage !== null || is_file(self::HOME . '/bin/elasticsearch');

        if (!$bInstalled) {
            return [
                'present'        => false,
                'version'        => null,
                'version_full'   => null,
                'version_source' => null,
                'package'        => null,
                'daemon'         => null,
                'error'          => null,
            ];
        }

        $aVersion = null;

        if ($aPackage !== null && $aPackage['version'] !== null) {
            $aVersion = $aPackage['version'] + ['version_source' => 'package'];
        } else {
            $aFromJar = $this->versionFromJar();

            if ($aFromJar !== null) {
                $aVersion = $aFromJar + ['version_source' => 'path'];
            }
        }

        return [
            'present'        => true,
            'version'        => $aVersion['version'] ?? null,
            'version_full'   => $aVersion['version_full'] ?? null,
            'version_source' => $aVersion['version_source'] ?? null,
            'package'        => $aPackage['name'] ?? null,
            'daemon'         => $this->daemonState(),
            'error'          => $aVersion === null ? 'Elasticsearch is installed but its version could not be read' : null,
        ];
    }

    /**
     * @return array{name: string, version: array{version: string, version_full: string}|null}|null
     */
    private function findPackage(): ?array
    {
        if (!System::commandExists('dpkg-query')) {
            return null;
        }

        try {
            $sLine = System::execString(
                'dpkg-query -W -f=\'${Package}\t${Status}\t${Version}\' ' . escapeshellarg(self::PACKAGE) . ' 2>/dev/null'
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
            'version' => self::parsePackageVersion($aParts[2]),
        ];
    }

    /**
     * @return array{version: string, version_full: string}|null
     */
    private function versionFromJar(): ?array
    {
        $aJars = glob(self::HOME . '/lib/elasticsearch-*.jar');

        if (!is_array($aJars)) {
            return null;
        }

        return self::parseJarVersion(array_map('basename', $aJars));
    }

    /**
     * The systemd state of the elasticsearch unit (`active`, `inactive`, `failed`, …), or null
     * when it cannot be asked
     */
    private function daemonState(): ?string
    {
        if (!System::commandExists('systemctl')) {
            return null;
        }

        //  is-active exits non-zero for anything but `active`, but still prints the state
        $aOutput = [];

        try {
            System::exec('systemctl is-active elasticsearch 2>/dev/null', $aOutput);
        } catch (CommandFailedException) {
        }

        $sState = trim((string) reset($aOutput));

        return preg_match('/^[a-z-]+$/', $sState) ? $sState : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
}
