<?php

namespace Shed\Cli\Tests\Entity\Heartbeat;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shed\Cli\Entity\Heartbeat\Accounts;

final class AccountsTest extends TestCase
{
    /**
     * Real keys, with the fingerprints an independent implementation (phpseclib, which `ssh-keygen
     * -lf` agrees with) computes for them — so a fingerprint here is the one an operator will paste
     * in from their own machine.
     */
    private const RSA = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABgQCl2OVBhTPfLfvCR7n4A6b9zeHk4iQWtnM3zKLPSI2GUFVSr9f1LSK9imwdg4KsS5sM2aFnZkAGENvVctxfDDLbuOFWeYLOFw9cI0PSeBJUUwgqKJk/oyvXmLzRoI4aaJC4M1ng4z+OUZ2RtVET4/5yjJ105VLSQLsgmO6B9u7DVZecNpJGySSu7BQ6kxZqNWk8F8EJjbJJKe8a5amVibXlRwaFtUeAOvWG8oLl6w93rPKyBJAaaizT4IW9/LheR+udN5sdV4/bk6yMvMsNfwktc0VPdLoZ9tjZfdWX7hBXabQFFfdbhEReJ5GtUVjgKuVf+M+P/9/BXzpNCZ9ocy4pHsZDQIf6f2R8+mn6dg1RUdAC17gav+t/N8TPs1ljZpOw3dshCKUjVtZh1ODosAHxhqds9pGyIIJtjsCYkbqJp7Uj7aWZseqGPHRsGl8qiKAhE3DURaYYdq/IMA380xacxVHPefgoHjNVi1ph3V3nUBwceLpUMBqmnkr5GX0xcCE=';
    private const RSA_FINGERPRINT = 'SHA256:BrQ6a3UwHkTzCDBfSWHbK+YVUwODjTpZgRTzedUFpj0';

    private const ED25519 = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAINEbA9n3eu+wrZi76qlazjAN8JSFJbD8WyGwog0EVhdv';
    private const ED25519_FINGERPRINT = 'SHA256:QhzsOffvEk1X8N3ClLWv+dT20Ukga2JfR4OcVY+P8vU';

    private const P384 = 'ecdsa-sha2-nistp384 AAAAE2VjZHNhLXNoYTItbmlzdHAzODQAAAAIbmlzdHAzODQAAABhBOSkCx7M1ixet3Qn/O7o+sB1lY2HO8D4QdPEeS4ANbK5Q90FeTymz4iY0R5QqCJDjzheeZAqWnLhSNTbAYE4mPsRqiH1+K3nMlYJMpLS4axrMdgur1eUCVn9VUq91JRPuA==';
    private const P384_FINGERPRINT = 'SHA256:y+RnCD0fG7tNaIyNlbRsCxMhUGv7+kT/opIULheJrIg';

    /**
     * @return array<string, array{string, array}>
     */
    public static function singleKeyLines(): array
    {
        return [
            'rsa with a comment' => [
                self::RSA . ' alice@laptop',
                [
                    'type' => 'ssh-rsa',
                    'bits' => 3072,
                    'fingerprint' => self::RSA_FINGERPRINT,
                    'comment' => 'alice@laptop',
                    'options' => [],
                ],
            ],
            'ed25519 with no comment' => [
                self::ED25519,
                [
                    'type' => 'ssh-ed25519',
                    'bits' => 256,
                    'fingerprint' => self::ED25519_FINGERPRINT,
                    'comment' => null,
                    'options' => [],
                ],
            ],
            'ecdsa, comment with spaces' => [
                self::P384 . ' Bob Smith (old laptop)',
                [
                    'type' => 'ecdsa-sha2-nistp384',
                    'bits' => 384,
                    'fingerprint' => self::P384_FINGERPRINT,
                    'comment' => 'Bob Smith (old laptop)',
                    'options' => [],
                ],
            ],
            'options, including a quoted value with a comma and a space' => [
                'from="10.0.0.0/8,192.168.1.1",command="/usr/bin/rsync --server, -e",no-pty ' . self::ED25519 . ' backup',
                [
                    'type' => 'ssh-ed25519',
                    'bits' => 256,
                    'fingerprint' => self::ED25519_FINGERPRINT,
                    'comment' => 'backup',
                    'options' => ['from="10.0.0.0/8,192.168.1.1"', 'command="/usr/bin/rsync --server, -e"', 'no-pty'],
                ],
            ],
            'a single bare option' => [
                'restrict ' . self::ED25519 . ' ci',
                [
                    'type' => 'ssh-ed25519',
                    'bits' => 256,
                    'fingerprint' => self::ED25519_FINGERPRINT,
                    'comment' => 'ci',
                    'options' => ['restrict'],
                ],
            ],
            'an escaped quote inside a quoted option' => [
                'command="echo \"hi there\"" ' . self::ED25519 . ' x',
                [
                    'type' => 'ssh-ed25519',
                    'bits' => 256,
                    'fingerprint' => self::ED25519_FINGERPRINT,
                    'comment' => 'x',
                    'options' => ['command="echo \"hi there\""'],
                ],
            ],
            'tab separated, surrounding whitespace' => [
                "  " . str_replace(' ', "\t", self::ED25519) . "\tdeploy@ci  ",
                [
                    'type' => 'ssh-ed25519',
                    'bits' => 256,
                    'fingerprint' => self::ED25519_FINGERPRINT,
                    'comment' => 'deploy@ci',
                    'options' => [],
                ],
            ],
        ];
    }

    #[DataProvider('singleKeyLines')]
    public function test_it_parses_a_key_line(string $sLine, array $aExpected): void
    {
        $aParsed = (new Accounts())->parseAuthorizedKeys($sLine);

        $this->assertSame([$aExpected], $aParsed['keys']);
        $this->assertSame(0, $aParsed['invalid']);
        $this->assertFalse($aParsed['truncated']);
    }

    /**
     * Never the key material: only the fingerprint identifies a key in the payload.
     */
    public function test_the_key_material_is_never_reported(): void
    {
        $aParsed = (new Accounts())->parseAuthorizedKeys(self::RSA . ' alice@laptop');

        $sEncoded = explode(' ', self::RSA)[1];

        $this->assertStringNotContainsString($sEncoded, json_encode($aParsed));
        $this->assertStringNotContainsString(substr($sEncoded, 20, 40), json_encode($aParsed));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function skippedLines(): array
    {
        return [
            'blank' => [''],
            'whitespace only' => ["   \t "],
            'comment' => ['# ' . self::ED25519 . ' old key, removed 2024'],
        ];
    }

    #[DataProvider('skippedLines')]
    public function test_it_skips_blank_lines_and_comments_without_counting_them(string $sLine): void
    {
        $aParsed = (new Accounts())->parseAuthorizedKeys($sLine);

        $this->assertSame([], $aParsed['keys']);
        $this->assertSame(0, $aParsed['invalid']);
    }

    /**
     * A line that is not a key is **counted**, never silently dropped — a file of damaged lines
     * must not read as an empty one.
     *
     * @return array<string, array{string}>
     */
    public static function invalidLines(): array
    {
        return [
            'garbage' => ['this is not a key'],
            'type with no key' => ['ssh-ed25519'],
            'not base64' => ['ssh-ed25519 !!!not-base64!!! someone'],
            'declared type does not match the encoded one' => ['ssh-rsa ' . explode(' ', self::ED25519)[1] . ' liar'],
            'truncated blob' => ['ssh-ed25519 AAAAC3NzaC1lZDI1 short'],
            'unterminated quoted option' => ['command="never closed ' . self::ED25519],
            'options but no key' => ['no-pty,restrict'],
        ];
    }

    #[DataProvider('invalidLines')]
    public function test_it_counts_lines_that_are_not_keys(string $sLine): void
    {
        $aParsed = (new Accounts())->parseAuthorizedKeys($sLine);

        $this->assertSame([], $aParsed['keys']);
        $this->assertSame(1, $aParsed['invalid']);
    }

    public function test_it_parses_a_whole_file(): void
    {
        $sFile = implode("\n", [
            '# managed by hand',
            self::RSA . ' alice@laptop',
            '',
            'garbage line',
            'no-port-forwarding ' . self::ED25519 . ' bob@desktop',
            self::P384,
        ]) . "\n";

        $aParsed = (new Accounts())->parseAuthorizedKeys($sFile);

        $this->assertSame(
            [self::RSA_FINGERPRINT, self::ED25519_FINGERPRINT, self::P384_FINGERPRINT],
            array_column($aParsed['keys'], 'fingerprint')
        );
        $this->assertSame(['alice@laptop', 'bob@desktop', null], array_column($aParsed['keys'], 'comment'));
        $this->assertSame(1, $aParsed['invalid']);
    }

    /**
     * A forced command is as capable of carrying a credential as a cron job or a sudoers entry is.
     */
    public function test_options_and_comments_are_redacted(): void
    {
        $aParsed = (new Accounts())->parseAuthorizedKeys(
            'command="/usr/bin/backup --password=hunter2",environment="API_TOKEN=abc123" ' . self::ED25519 . ' TOKEN=s3cr3t'
        );

        $sJson = json_encode($aParsed);

        $this->assertStringNotContainsString('hunter2', $sJson);
        $this->assertStringNotContainsString('abc123', $sJson);
        $this->assertStringNotContainsString('s3cr3t', $sJson);
        $this->assertStringContainsString('[REDACTED]', $sJson);
    }

    public function test_it_parses_the_effective_sshd_configuration(): void
    {
        $sOutput = implode("\n", [
            'port 22',
            'permitrootlogin without-password',
            'pubkeyauthentication yes',
            'passwordauthentication no',
            'kbdinteractiveauthentication no',
            'authorizedkeysfile .ssh/authorized_keys .ssh/authorized_keys2',
            'authorizedkeyscommand none',
            'authorizedkeyscommanduser none',
            'allowusers deploy alice@10.0.0.*',
            'allowusers bob',
            'denygroups contractors',
        ]);

        $aSshd = (new Accounts())->parseSshdConfig($sOutput);

        $this->assertSame([
            'permit_root_login' => 'without-password',
            'pubkey_authentication' => 'yes',
            'password_authentication' => 'no',
            'kbd_interactive_authentication' => 'no',
            'authorized_keys_files' => ['.ssh/authorized_keys', '.ssh/authorized_keys2'],
            'authorized_keys_command' => null,
            'authorized_keys_command_user' => null,
            'allow_users' => ['deploy', 'alice@10.0.0.*', 'bob'],
            'deny_groups' => ['contractors'],
        ], $aSshd);
    }

    /**
     * GCP OS Login and EC2 Instance Connect hand sshd keys from a command, not a file — so the
     * files on disk are not the whole picture, and the command is what says so.
     */
    public function test_an_authorized_keys_command_is_reported(): void
    {
        $aSshd = (new Accounts())->parseSshdConfig(implode("\n", [
            'authorizedkeysfile none',
            'authorizedkeyscommand /usr/bin/google_authorized_keys',
            'authorizedkeyscommanduser root',
        ]));

        $this->assertSame([], $aSshd['authorized_keys_files']);
        $this->assertSame('/usr/bin/google_authorized_keys', $aSshd['authorized_keys_command']);
        $this->assertSame('root', $aSshd['authorized_keys_command_user']);
    }
}
