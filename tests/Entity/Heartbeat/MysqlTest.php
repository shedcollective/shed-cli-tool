<?php

namespace Shed\Cli\Tests\Entity\Heartbeat;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shed\Cli\Entity\Heartbeat\Mysql;

final class MysqlTest extends TestCase
{
    /**
     * @return array<string, array{string, array{engine: string, version: string, version_full: string}}>
     */
    public static function banners(): array
    {
        return [
            'ubuntu mysql 8.0' => [
                'mysqld  Ver 8.0.42-0ubuntu0.22.04.1 for Linux on x86_64 ((Ubuntu))',
                [
                    'engine'       => 'mysql',
                    'version'      => '8.0.42',
                    'version_full' => '8.0.42-0ubuntu0.22.04.1',
                ],
            ],
            'community mysql without debian suffix' => [
                'mysqld  Ver 8.0.36 for Linux on x86_64 (MySQL Community Server - GPL)',
                [
                    'engine'       => 'mysql',
                    'version'      => '8.0.36',
                    'version_full' => '8.0.36',
                ],
            ],
            'mysql 5.7' => [
                'mysqld  Ver 5.7.42-0ubuntu0.18.04.1 for Linux on x86_64 ((Ubuntu))',
                [
                    'engine'       => 'mysql',
                    'version'      => '5.7.42',
                    'version_full' => '5.7.42-0ubuntu0.18.04.1',
                ],
            ],
            'ubuntu mariadb behind the mysqld symlink' => [
                'mysqld  Ver 10.11.6-MariaDB-0ubuntu0.24.04.1 for debian-linux-gnu on x86_64 (Ubuntu 24.04)',
                [
                    'engine'       => 'mariadb',
                    'version'      => '10.11.6',
                    'version_full' => '10.11.6-MariaDB-0ubuntu0.24.04.1',
                ],
            ],
            'mariadbd binary' => [
                'mariadbd  Ver 10.11.8-MariaDB-0ubuntu0.24.04.1 for debian-linux-gnu on x86_64 (Ubuntu 24.04)',
                [
                    'engine'       => 'mariadb',
                    'version'      => '10.11.8',
                    'version_full' => '10.11.8-MariaDB-0ubuntu0.24.04.1',
                ],
            ],
            'absolute path prefix' => [
                '/usr/sbin/mysqld  Ver 10.6.16-MariaDB-0ubuntu0.22.04.1 for debian-linux-gnu on x86_64 (Ubuntu 22.04)',
                [
                    'engine'       => 'mariadb',
                    'version'      => '10.6.16',
                    'version_full' => '10.6.16-MariaDB-0ubuntu0.22.04.1',
                ],
            ],
        ];
    }

    #[DataProvider('banners')]
    public function test_it_parses_a_version_banner(string $banner, array $expected): void
    {
        $this->assertSame($expected, Mysql::parseBanner($banner));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unrecognisedBanners(): array
    {
        return [
            'empty' => [''],
            'help text' => ['Usage: mysqld [OPTIONS]'],
            'no Ver token' => ['mysqld  8.0.42'],
            'Ver without a semver' => ['mysqld  Ver unknown-build'],
        ];
    }

    #[DataProvider('unrecognisedBanners')]
    public function test_it_rejects_an_unrecognised_banner(string $banner): void
    {
        $this->assertNull(Mysql::parseBanner($banner));
    }

    /**
     * @return array<string, array{string, array{version: string, version_full: string}}>
     */
    public static function packageVersions(): array
    {
        return [
            'ubuntu mysql' => [
                '8.0.42-0ubuntu0.22.04.1',
                [
                    'version'      => '8.0.42',
                    'version_full' => '8.0.42-0ubuntu0.22.04.1',
                ],
            ],
            'mariadb with epoch' => [
                '1:10.11.6-0ubuntu0.24.04.1',
                [
                    'version'      => '10.11.6',
                    'version_full' => '10.11.6-0ubuntu0.24.04.1',
                ],
            ],
        ];
    }

    #[DataProvider('packageVersions')]
    public function test_it_parses_a_dpkg_version(string $raw, array $expected): void
    {
        $this->assertSame($expected, Mysql::parsePackageVersion($raw));
    }

    public function test_it_rejects_an_unrecognised_dpkg_version(): void
    {
        $this->assertNull(Mysql::parsePackageVersion('not-a-version'));
    }
}
