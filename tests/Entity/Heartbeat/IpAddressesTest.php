<?php

namespace Shed\Cli\Tests\Entity\Heartbeat;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shed\Cli\Entity\Heartbeat\IpAddresses;

final class IpAddressesTest extends TestCase
{
    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function interfaceLists(): array
    {
        return [
            'single ipv4' => ['10.128.0.2', ['10.128.0.2']],
            'several addresses' => [
                '10.128.0.2 172.17.0.1 2001:db8::1',
                ['10.128.0.2', '172.17.0.1', '2001:db8::1'],
            ],
            'extra whitespace' => [
                "  10.0.0.5 \t 192.168.1.1\n",
                ['10.0.0.5', '192.168.1.1'],
            ],
            'empty' => ['', []],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('interfaceLists')]
    public function test_it_parses_hostname_dash_i_output(string $output, array $expected): void
    {
        $this->assertSame($expected, IpAddresses::parseInterfaceList($output));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function publicIpv4s(): array
    {
        return [
            'rfc1918 10/8' => ['10.128.0.2', false],
            'rfc1918 172.16/12' => ['172.16.0.4', false],
            'rfc1918 192.168/16' => ['192.168.86.26', false],
            'cgnat' => ['100.64.1.2', false],
            'loopback' => ['127.0.0.1', false],
            'link local' => ['169.254.1.1', false],
            'ipv6' => ['2001:db8::1', false],
            'globally routable' => ['8.8.8.8', true],
            'another public' => ['1.1.1.1', true],
        ];
    }

    #[DataProvider('publicIpv4s')]
    public function test_it_knows_a_public_ipv4(string $address, bool $expected): void
    {
        $this->assertSame($expected, IpAddresses::isPublicIpv4($address));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function scopes(): array
    {
        return [
            'public v4' => ['8.8.8.8', 'external'],
            'rfc1918' => ['10.0.0.5', 'private'],
            'cgnat' => ['100.64.1.2', 'private'],
            'global ipv6' => ['2001:4860:4860::8888', 'external'],
            'ula ipv6' => ['fd12:3456:789a::1', 'private'],
            'link local ipv6' => ['fe80::1', 'private'],
        ];
    }

    #[DataProvider('scopes')]
    public function test_it_classifies_scope(string $address, string $expected): void
    {
        $this->assertSame($expected, IpAddresses::scopeOf($address));
    }

    public function test_it_rejects_a_non_address(): void
    {
        $this->assertNull(IpAddresses::scopeOf('not-an-ip'));
        $this->assertNull(IpAddresses::scopeOf(''));
    }

    /**
     * @return array<string, array{string, string, string|null}>
     */
    public static function dmiVendors(): array
    {
        return [
            'gce' => ['Google', 'Google Compute Engine', 'gcp'],
            'google vendor only' => ['Google', '', 'gcp'],
            'ec2' => ['Amazon EC2', 'Amazon EC2', 'ec2'],
            'amazon vendor' => ['Amazon', 'HVM', 'ec2'],
            'bare metal' => ['Dell Inc.', 'PowerEdge', null],
            'empty' => ['', '', null],
        ];
    }

    #[DataProvider('dmiVendors')]
    public function test_it_detects_cloud_from_dmi(string $vendor, string $product, ?string $expected): void
    {
        $this->assertSame($expected, IpAddresses::cloudFromDmi($vendor, $product));
    }

    public function test_a_public_address_on_the_nic_wins(): void
    {
        $snapshot = IpAddresses::select(
            ['8.8.8.8', '10.0.0.5'],
            '1.1.1.1',
            'gcp-metadata'
        );

        $this->assertSame('8.8.8.8', $snapshot['primary']);
        $this->assertSame('8.8.8.8', $snapshot['addresses'][0]['address']);
        $this->assertSame(
            [
                ['address' => '8.8.8.8', 'scope' => 'external', 'source' => 'interface'],
                ['address' => '10.0.0.5', 'scope' => 'private', 'source' => 'interface'],
                ['address' => '1.1.1.1', 'scope' => 'external', 'source' => 'gcp-metadata'],
            ],
            $snapshot['addresses']
        );
    }

    public function test_metadata_wins_when_the_nic_is_all_private(): void
    {
        $snapshot = IpAddresses::select(
            ['10.128.0.2', '172.17.0.1'],
            '34.1.2.3',
            'gcp-metadata'
        );

        $this->assertSame('34.1.2.3', $snapshot['primary']);
        $this->assertSame(
            [
                ['address' => '34.1.2.3', 'scope' => 'external', 'source' => 'gcp-metadata'],
                ['address' => '10.128.0.2', 'scope' => 'private', 'source' => 'interface'],
                ['address' => '172.17.0.1', 'scope' => 'private', 'source' => 'interface'],
            ],
            $snapshot['addresses']
        );
    }

    public function test_ec2_metadata_is_labelled(): void
    {
        $snapshot = IpAddresses::select(['10.0.0.5'], '3.4.5.6', 'ec2-metadata');

        $this->assertSame('3.4.5.6', $snapshot['primary']);
        $this->assertSame('ec2-metadata', $snapshot['addresses'][0]['source']);
    }

    public function test_without_metadata_it_keeps_the_first_private_ipv4(): void
    {
        $snapshot = IpAddresses::select(['10.128.0.2', '172.17.0.1']);

        $this->assertSame('10.128.0.2', $snapshot['primary']);
        $this->assertSame(
            [
                ['address' => '10.128.0.2', 'scope' => 'private', 'source' => 'interface'],
                ['address' => '172.17.0.1', 'scope' => 'private', 'source' => 'interface'],
            ],
            $snapshot['addresses']
        );
    }

    public function test_ipv6_is_listed_but_not_primary_when_an_ipv4_exists(): void
    {
        $snapshot = IpAddresses::select([
            '2001:4860:4860::8888',
            '10.0.0.5',
        ]);

        $this->assertSame('10.0.0.5', $snapshot['primary']);
        $this->assertSame('10.0.0.5', $snapshot['addresses'][0]['address']);
        $this->assertSame(
            [
                ['address' => '10.0.0.5', 'scope' => 'private', 'source' => 'interface'],
                ['address' => '2001:4860:4860::8888', 'scope' => 'external', 'source' => 'interface'],
            ],
            $snapshot['addresses']
        );
    }

    public function test_a_lone_ipv6_is_the_last_resort_primary(): void
    {
        $snapshot = IpAddresses::select(['2001:4860:4860::8888']);

        $this->assertSame('2001:4860:4860::8888', $snapshot['primary']);
    }

    public function test_cgnat_is_not_a_public_primary(): void
    {
        $snapshot = IpAddresses::select(['100.64.1.2'], '8.8.4.4', 'gcp-metadata');

        $this->assertSame('8.8.4.4', $snapshot['primary']);
    }

    public function test_a_duplicate_metadata_address_is_not_listed_twice(): void
    {
        $snapshot = IpAddresses::select(['8.8.8.8'], '8.8.8.8', 'gcp-metadata');

        $this->assertCount(1, $snapshot['addresses']);
        $this->assertSame('interface', $snapshot['addresses'][0]['source']);
    }

    public function test_garbage_tokens_are_skipped(): void
    {
        $snapshot = IpAddresses::select(['not-an-ip', '10.0.0.5']);

        $this->assertSame('10.0.0.5', $snapshot['primary']);
        $this->assertCount(1, $snapshot['addresses']);
    }
}
