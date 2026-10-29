<?php

namespace Shed\Cli\Tests\Entity\Heartbeat;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shed\Cli\Entity\Heartbeat\Docker;

final class DockerTest extends TestCase
{
    /**
     * @return array<string, array{string, array{version: string, version_full: string}}>
     */
    public static function banners(): array
    {
        return [
            'docker-ce' => [
                'Docker version 27.3.1, build 41ca978',
                ['version' => '27.3.1', 'version_full' => '27.3.1'],
            ],
            'ubuntu docker.io' => [
                'Docker version 24.0.7, build 24.0.7-0ubuntu4.1',
                ['version' => '24.0.7', 'version_full' => '24.0.7'],
            ],
            'pre-semver release' => [
                'Docker version 20.10.24+dfsg1, build 297e128',
                ['version' => '20.10.24', 'version_full' => '20.10.24+dfsg1'],
            ],
        ];
    }

    #[DataProvider('banners')]
    public function test_it_parses_a_version_banner(string $banner, array $expected): void
    {
        $this->assertSame($expected, Docker::parseBanner($banner));
    }

    public function test_it_rejects_an_unrecognised_banner(): void
    {
        $this->assertNull(Docker::parseBanner(''));
        $this->assertNull(Docker::parseBanner('dockerd: command not found'));
        $this->assertNull(Docker::parseBanner('Docker version dev, build unknown'));
    }

    /**
     * @return array<string, array{string, array{version: string, version_full: string}}>
     */
    public static function packageVersions(): array
    {
        return [
            'docker-ce with an epoch' => [
                '5:27.3.1-1~ubuntu.24.04~noble',
                ['version' => '27.3.1', 'version_full' => '27.3.1-1~ubuntu.24.04~noble'],
            ],
            'ubuntu docker.io' => [
                '24.0.7-0ubuntu4.1',
                ['version' => '24.0.7', 'version_full' => '24.0.7-0ubuntu4.1'],
            ],
        ];
    }

    #[DataProvider('packageVersions')]
    public function test_it_parses_a_package_version(string $raw, array $expected): void
    {
        $this->assertSame($expected, Docker::parsePackageVersion($raw));
    }

    public function test_it_parses_daemon_info(): void
    {
        $this->assertSame(
            [
                'version' => ['version' => '27.3.1', 'version_full' => '27.3.1'],
                'running' => 4,
                'total'   => 6,
            ],
            Docker::parseInfo("27.3.1|4|6\n")
        );
    }

    public function test_daemon_info_it_cannot_read_is_unknown_not_zero(): void
    {
        $this->assertSame(
            ['version' => null, 'running' => null, 'total' => null],
            Docker::parseInfo('')
        );

        //  What `docker info --format` prints when the daemon is down
        $this->assertSame(
            ['version' => null, 'running' => null, 'total' => null],
            Docker::parseInfo('|0|0')
        );

        $this->assertSame(
            ['version' => ['version' => '27.3.1', 'version_full' => '27.3.1'], 'running' => null, 'total' => null],
            Docker::parseInfo('27.3.1|<no value>|')
        );
    }

    public function test_an_idle_daemon_reports_zero_containers(): void
    {
        $this->assertSame(0, Docker::parseInfo('27.3.1|0|0')['running']);
    }
}
