<?php

namespace Shed\Cli\Tests\Entity\Heartbeat;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shed\Cli\Entity\Heartbeat\Elasticsearch;

final class ElasticsearchTest extends TestCase
{
    /**
     * @return array<string, array{string, array{version: string, version_full: string}}>
     */
    public static function packageVersions(): array
    {
        return [
            'elastic 8.x' => [
                '8.15.0',
                ['version' => '8.15.0', 'version_full' => '8.15.0'],
            ],
            'elastic 7.x' => [
                '7.17.24',
                ['version' => '7.17.24', 'version_full' => '7.17.24'],
            ],
            'with an epoch and a debian revision' => [
                '1:8.15.0-1',
                ['version' => '8.15.0', 'version_full' => '8.15.0-1'],
            ],
        ];
    }

    #[DataProvider('packageVersions')]
    public function test_it_parses_a_package_version(string $raw, array $expected): void
    {
        $this->assertSame($expected, Elasticsearch::parsePackageVersion($raw));
    }

    public function test_it_rejects_an_unrecognised_package_version(): void
    {
        $this->assertNull(Elasticsearch::parsePackageVersion(''));
        $this->assertNull(Elasticsearch::parsePackageVersion('8.15'));
    }

    public function test_it_takes_the_version_from_the_server_jar_only(): void
    {
        $this->assertSame(
            ['version' => '8.15.0', 'version_full' => '8.15.0'],
            Elasticsearch::parseJarVersion([
                'elasticsearch-core-8.15.0.jar',
                'elasticsearch-x-content-8.15.0.jar',
                'elasticsearch-8.15.0.jar',
                'lucene-core-9.11.1.jar',
            ])
        );
    }

    public function test_it_reads_a_pre_release_jar(): void
    {
        $this->assertSame(
            ['version' => '9.0.0', 'version_full' => '9.0.0-SNAPSHOT'],
            Elasticsearch::parseJarVersion(['elasticsearch-9.0.0-SNAPSHOT.jar'])
        );
    }

    public function test_no_server_jar_is_unknown(): void
    {
        $this->assertNull(Elasticsearch::parseJarVersion([]));
        $this->assertNull(Elasticsearch::parseJarVersion(['elasticsearch-core-8.15.0.jar']));
    }
}
