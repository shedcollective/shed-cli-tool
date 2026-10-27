<?php

namespace Shed\Cli\Tests\Entity\Heartbeat;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shed\Cli\Entity\Heartbeat\Sudoers;

final class SudoersTest extends TestCase
{
    /**
     * @return array<string, array{string, array}>
     */
    public static function singleEntryLines(): array
    {
        return [
            'passwordless blanket grant' => [
                'deploy ALL=(ALL) NOPASSWD: ALL',
                [
                    'subject' => 'deploy',
                    'host' => 'ALL',
                    'run_as' => 'ALL',
                    'nopasswd' => true,
                    'commands' => ['ALL'],
                ],
            ],
            'group grant, no tags' => [
                '%sudo ALL=(ALL:ALL) ALL',
                [
                    'subject' => '%sudo',
                    'host' => 'ALL',
                    'run_as' => 'ALL:ALL',
                    'nopasswd' => false,
                    'commands' => ['ALL'],
                ],
            ],
            'no run-as spec (defaults to root)' => [
                'deploy ALL = NOPASSWD: ALL',
                [
                    'subject' => 'deploy',
                    'host' => 'ALL',
                    'run_as' => null,
                    'nopasswd' => true,
                    'commands' => ['ALL'],
                ],
            ],
            'multiple tags' => [
                'ubuntu ALL=(ALL) NOPASSWD: NOEXEC: ALL',
                [
                    'subject' => 'ubuntu',
                    'host' => 'ALL',
                    'run_as' => 'ALL',
                    'nopasswd' => true,
                    'commands' => ['ALL'],
                ],
            ],
            'scoped, comma separated commands' => [
                'deploy ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart nginx, /usr/bin/systemctl reload apache2',
                [
                    'subject' => 'deploy',
                    'host' => 'ALL',
                    'run_as' => 'ALL',
                    'nopasswd' => true,
                    'commands' => ['/usr/bin/systemctl restart nginx', '/usr/bin/systemctl reload apache2'],
                ],
            ],
        ];
    }

    #[DataProvider('singleEntryLines')]
    public function test_it_parses_a_privilege_specification(string $line, array $expected): void
    {
        $sudoers = new Sudoers();

        $this->assertSame([$expected], $sudoers->parseSudoers($line));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function skippedLines(): array
    {
        return [
            'blank' => [''],
            'comment' => ['# deploy ALL=(ALL) NOPASSWD: ALL'],
            'Defaults tuning' => ['Defaults env_reset'],
            'Defaults for one user' => ['Defaults:deploy !requiretty'],
            'a User_Alias definition' => ['User_Alias ADMINS = deploy, ubuntu'],
            'a Cmnd_Alias definition' => ['Cmnd_Alias SERVICES = /usr/bin/systemctl'],
            'legacy includedir directive' => ['#includedir /etc/sudoers.d'],
            'modern include directive' => ['@includedir /etc/sudoers.d'],
        ];
    }

    #[DataProvider('skippedLines')]
    public function test_it_skips_lines_which_are_not_privilege_specifications(string $line): void
    {
        $sudoers = new Sudoers();

        $this->assertSame([], $sudoers->parseSudoers($line));
    }

    /**
     * A sudoers file is a list of privilege specifications, one per line; every one found is
     * reported, not just the first.
     */
    public function test_it_parses_every_entry_in_a_file(): void
    {
        $sudoers = new Sudoers();

        $contents = <<<'SUDOERS'
            # /etc/sudoers
            Defaults env_reset
            root ALL=(ALL:ALL) ALL
            %sudo ALL=(ALL:ALL) ALL

            #includedir /etc/sudoers.d
            SUDOERS;

        $entries = $sudoers->parseSudoers($contents);

        $this->assertCount(2, $entries);
        $this->assertSame('root', $entries[0]['subject']);
        $this->assertSame('%sudo', $entries[1]['subject']);
    }

    /**
     * Commands are credential-redacted the same way a cron job's command is, since a sudoers
     * entry is just as capable of having a secret written into it.
     */
    public function test_commands_are_redacted(): void
    {
        $sudoers = new Sudoers();

        $entries = $sudoers->parseSudoers(
            'deploy ALL=(ALL) NOPASSWD: /usr/bin/curl -H "Authorization: Bearer sk_live_9aB3xQ" https://example.com'
        );

        $this->assertStringContainsString('[REDACTED]', $entries[0]['commands'][0]);
        $this->assertStringNotContainsString('sk_live_9aB3xQ', $entries[0]['commands'][0]);
    }
}
