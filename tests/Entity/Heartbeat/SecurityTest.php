<?php

namespace Shed\Cli\Tests\Entity\Heartbeat;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shed\Cli\Entity\Heartbeat\Security;

final class SecurityTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function loginLines(): array
    {
        return [
            'util-linux session' => [
                'ubuntu   pts/0        203.0.113.4      Mon Nov 16 15:04:01 2026 - Mon Nov 16 15:09:12 2026  (00:05)',
                'ubuntu   pts/0        203.0.113.4      Mon Nov 16 15:04:01 2026 - Mon Nov 16 15:09:12 2026  (00:05)',
            ],
            'wtmpdb session' => [
                "deploy   pts/0        203.0.113.8      Mon Nov 16 15:04:01 2026 - still logged in\n",
                'deploy   pts/0        203.0.113.8      Mon Nov 16 15:04:01 2026 - still logged in',
            ],
            'reboot record' => [
                'reboot   system boot  6.12.0-1-amd64   Mon Nov 16 15:01:02 2026 - still running',
                'reboot   system boot  6.12.0-1-amd64   Mon Nov 16 15:01:02 2026 - still running',
            ],
        ];
    }

    #[DataProvider('loginLines')]
    public function test_it_keeps_a_login_line(string $line, string $expected): void
    {
        $this->assertSame($expected, Security::parseLastLogin($line));
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function emptyDatabases(): array
    {
        return [
            'nothing'            => [null],
            'blank'              => [''],
            'whitespace'         => ["  \n"],
            'wtmpdb empty'       => ['wtmpdb has no entries'],
            'named database'     => ['/var/log/wtmp.db has no entries'],
            'util-linux footer'  => ['wtmp begins Mon Nov 16 15:00:00 2026'],
            'wtmpdb footer'      => ['wtmpdb begins Mon Nov 16 15:00:00 2026'],
        ];
    }

    #[DataProvider('emptyDatabases')]
    public function test_an_empty_database_is_unknown(?string $line): void
    {
        $this->assertNull(Security::parseLastLogin($line));
    }
}
