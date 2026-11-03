<?php

namespace Shed\Cli\Tests\Entity\Heartbeat;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shed\Cli\Entity\Heartbeat\Containers;

final class ContainersTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string|null}>
     */
    public static function banners(): array
    {
        return [
            'php cli'           => ['php', "PHP 8.4.27 (cli) (built: Oct 21 2026 22:52:58) (NTS)\nCopyright (c) The PHP Group\nZend Engine v4.4.27", '8.4.27'],
            'php-fpm'           => ['php_fpm', 'PHP 8.2.35 (fpm-fcgi) (built: Oct  3 2026 11:20:01)', '8.2.35'],
            'php with a warning first' => ['php', "PHP Warning:  Module \"redis\" is already loaded in Unknown on line 0\nPHP 8.5.12 (cli) (built: Oct 1 2026)", '8.5.12'],
            'node'              => ['node', "v20.11.1\n", '20.11.1'],
            'npm'               => ['npm', "10.2.4\n", '10.2.4'],
            'mysql'             => ['mysql', 'mysqld  Ver 8.0.36 for Linux on x86_64 (MySQL Community Server - GPL)', '8.0.36'],
            'mariadb'           => ['mariadb', 'mariadbd  Ver 11.4.2-MariaDB-ubu2404 for debian-linux-gnu on x86_64 (mariadb.org binary distribution)', '11.4.2'],
            'old mariadb'       => ['mariadb', 'mysqld  Ver 10.3.39-MariaDB-0+deb10u1 for debian-linux-gnu on x86_64 (Debian 10)', '10.3.39'],
            'postgres'          => ['postgres', 'postgres (PostgreSQL) 16.2 (Debian 16.2-1.pgdg120+2)', '16.2'],
            'redis'             => ['redis', 'Redis server v=7.2.4 sha=00000000:0 malloc=jemalloc-5.3.0 bits=64 build=5fe0e6c6b1d7a8e0', '7.2.4'],
            'caddy'             => ['caddy', 'v2.7.6 h1:w0NymbG2m9PcvKWsrXO6EEkY9Ru4FJK8uQbYcev1p3A=', '2.7.6'],
            'nginx'             => ['nginx', 'nginx version: nginx/1.25.4', '1.25.4'],
            'apache'            => ['apache', "[Thu Oct 31 08:00:00 2026] [core:warn] AH00111: Config variable \${APACHE_RUN_DIR} is not defined\nServer version: Apache/2.4.58 (Debian)\nServer built:   2026-07-12", '2.4.58'],
            'unrecognised'      => ['php', 'Segmentation fault', null],
        ];
    }

    #[DataProvider('banners')]
    public function test_it_parses_a_runtime_banner(string $package, string $output, ?string $expected): void
    {
        $this->assertSame($expected, Containers::parseVersion($package, $output)['version']);
    }

    public function test_the_raw_banner_is_the_line_naming_the_runtime(): void
    {
        $this->assertSame(
            'Server version: Apache/2.4.58 (Debian)',
            Containers::parseVersion('apache', "AH00111: warning\nServer version: Apache/2.4.58 (Debian)\nServer built: x")['raw']
        );
    }

    public function test_the_raw_banner_is_capped_and_printable(): void
    {
        $raw = Containers::parseVersion('php', 'PHP 8.4.1 ' . str_repeat("\xff", 500))['raw'];

        $this->assertSame(200, strlen((string) $raw));
        $this->assertSame(1, preg_match('//u', (string) $raw), 'The payload must stay valid UTF-8');
    }

    public function test_it_splits_the_probe_scripts_output(): void
    {
        $answers = Containers::parseProbeOutput([
            '@@shed-probe:os',
            'ID=alpine',
            'VERSION_ID=3.19.1',
            '@@shed-probe:php',
            'PHP 8.4.27 (cli)',
            '@@shed-probe:mysqld',
            'mysqld  Ver 11.4.2-MariaDB',
        ]);

        $this->assertSame([
            'os'     => "ID=alpine\nVERSION_ID=3.19.1\n",
            'php'    => "PHP 8.4.27 (cli)\n",
            'mysqld' => "mysqld  Ver 11.4.2-MariaDB\n",
        ], $answers);
    }

    public function test_output_without_the_os_marker_means_the_script_never_ran(): void
    {
        $this->assertNull(Containers::parseProbeOutput([
            'OCI runtime exec failed: exec failed: unable to start container process: exec: "sh": executable file not found in $PATH: unknown',
        ]));
    }

    public function test_every_package_is_reported_and_a_missing_one_is_null(): void
    {
        $packages = Containers::packages(['php' => "PHP 8.4.27 (cli)\n"]);

        $this->assertSame(Containers::PACKAGES, array_keys($packages));
        $this->assertSame(['version' => '8.4.27', 'raw' => 'PHP 8.4.27 (cli)'], $packages['php']);
        $this->assertNull($packages['node']);
        $this->assertNull($packages['mysql']);
    }

    public function test_mariadb_is_told_from_mysql_by_its_banner_not_its_filename(): void
    {
        $packages = Containers::packages([
            'mariadbd' => "mariadbd  Ver 11.4.2-MariaDB-ubu2404 for debian-linux-gnu\n",
            //  MariaDB images ship a mysqld symlink too
            'mysqld'   => "mysqld  Ver 11.4.2-MariaDB-ubu2404 for debian-linux-gnu\n",
        ]);

        $this->assertSame('11.4.2', $packages['mariadb']['version'] ?? null);
        $this->assertNull($packages['mysql']);

        $packages = Containers::packages(['mysqld' => "mysqld  Ver 8.4.3 for Linux on x86_64 (MySQL Community Server - GPL)\n"]);

        $this->assertSame('8.4.3', $packages['mysql']['version'] ?? null);
        $this->assertNull($packages['mariadb']);
    }

    public function test_it_keeps_only_three_fields_of_os_release(): void
    {
        $this->assertSame(
            ['id' => 'debian', 'version' => '12', 'pretty_name' => 'Debian GNU/Linux 12 (bookworm)'],
            Containers::parseOsRelease("PRETTY_NAME=\"Debian GNU/Linux 12 (bookworm)\"\nNAME=\"Debian GNU/Linux\"\nVERSION_ID=\"12\"\nVERSION=\"12 (bookworm)\"\nID=debian\nHOME_URL=\"https://www.debian.org/\"\n")
        );

        $this->assertSame(
            ['id' => 'alpine', 'version' => '3.19.1', 'pretty_name' => null],
            Containers::parseOsRelease("ID=alpine\nVERSION_ID=3.19.1\n")
        );

        $this->assertNull(Containers::parseOsRelease(''));
    }

    public function test_the_probe_script_is_valid_posix_sh_and_reports_os_first(): void
    {
        $output = [];
        exec('sh -c ' . escapeshellarg(Containers::script()) . ' 2>&1', $output, $exit);

        $this->assertSame(0, $exit, implode("\n", $output));

        $answers = Containers::parseProbeOutput($output);

        $this->assertNotNull($answers);
        $this->assertSame('os', array_key_first($answers));
    }

    public function test_the_probe_script_reads_nothing_but_os_release(): void
    {
        $script = Containers::script();

        $this->assertStringNotContainsString('env', preg_replace('/\b(?:timeout|command -v)\b/', '', $script) ?? '');
        $this->assertStringNotContainsString('/proc', $script);
        $this->assertSame(2, substr_count($script, 'cat '), 'The only files read are the two os-release locations');
    }
}
