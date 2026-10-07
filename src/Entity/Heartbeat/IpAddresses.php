<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Helper\System;

/**
 * Class IpAddresses
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class IpAddresses implements \JsonSerializable
{
    /**
     * How long a wedged metadata probe may stall the heartbeat
     *
     * @var int
     */
    private const METADATA_TIMEOUT = 2;

    /**
     * @var array{primary: string|null, addresses: list<array{address: string, scope: string, source: string}>}|null
     */
    private ?array $snapshot = null;

    /**
     * The address list sent as `ip_addresses`
     *
     * @return array{addresses: list<array{address: string, scope: string, source: string}>}
     */
    public function get(): array
    {
        return [
            'addresses' => $this->snapshot()['addresses'],
        ];
    }

    /**
     * The address stored as the heartbeat's `ip` field
     *
     * Public IPv4 on an interface wins, then a cloud-metadata external IP, then
     * the first IPv4 on an interface. IPv6 is listed but never chosen while an
     * IPv4 exists, so a dual-stack host does not flip its identity.
     *
     * @return string|null
     */
    public function primary(): ?string
    {
        return $this->snapshot()['primary'];
    }

    /**
     * Splits `hostname -I` output into address tokens
     *
     * @param string $sOutput One line of space-separated addresses
     *
     * @return list<string>
     */
    public static function parseInterfaceList(string $sOutput): array
    {
        $aTokens = preg_split('/\s+/', trim($sOutput), -1, PREG_SPLIT_NO_EMPTY);

        return $aTokens === false ? [] : array_values($aTokens);
    }

    /**
     * Whether this IPv4 is globally routable
     *
     * Skips loopback, link-local, RFC1918, and CGNAT `100.64.0.0/10`. IPv6 is
     * never treated as a public IPv4.
     *
     * @param string $sAddress
     *
     * @return bool
     */
    public static function isPublicIpv4(string $sAddress): bool
    {
        if (filter_var($sAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        return self::isPublic($sAddress);
    }

    /**
     * Whether this address is globally routable (v4 or v6)
     *
     * @param string $sAddress
     *
     * @return bool
     */
    public static function isPublic(string $sAddress): bool
    {
        if (self::isCgnat($sAddress)) {
            return false;
        }

        return filter_var(
            $sAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * `external` for a globally routable address, `private` otherwise
     *
     * @param string $sAddress
     *
     * @return string|null Null when the token is not an IP
     */
    public static function scopeOf(string $sAddress): ?string
    {
        if (filter_var($sAddress, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return self::isPublic($sAddress) ? 'external' : 'private';
    }

    /**
     * Identifies GCE / EC2 from DMI strings, or null on anything else
     *
     * @param string $sSysVendor   Contents of `/sys/class/dmi/id/sys_vendor`
     * @param string $sProductName Contents of `/sys/class/dmi/id/product_name`
     *
     * @return string|null `gcp`, `ec2`, or null
     */
    public static function cloudFromDmi(string $sSysVendor, string $sProductName = ''): ?string
    {
        $sHaystack = strtolower($sSysVendor . ' ' . $sProductName);

        if (str_contains($sHaystack, 'google')) {
            return 'gcp';
        }

        if (str_contains($sHaystack, 'amazon') || str_contains($sHaystack, 'ec2')) {
            return 'ec2';
        }

        return null;
    }

    /**
     * Picks the heartbeat `ip` and builds the address list
     *
     * Pure: no shell, no HTTP. The collector feeds it interface tokens plus an
     * optional metadata address.
     *
     * @param list<string> $aInterfaceAddresses Addresses on the guest NICs
     * @param string|null  $sMetadataAddress    External IP from cloud metadata
     * @param string|null  $sMetadataSource     `gcp-metadata` or `ec2-metadata`
     *
     * @return array{primary: string|null, addresses: list<array{address: string, scope: string, source: string}>}
     */
    public static function select(
        array $aInterfaceAddresses,
        ?string $sMetadataAddress = null,
        ?string $sMetadataSource = null
    ): array {
        $aAddresses = [];
        $aSeen      = [];

        foreach ($aInterfaceAddresses as $sAddress) {
            self::pushAddress($aAddresses, $aSeen, $sAddress, 'interface');
        }

        if (is_string($sMetadataAddress) && is_string($sMetadataSource)) {
            self::pushAddress($aAddresses, $aSeen, $sMetadataAddress, $sMetadataSource);
        }

        $sPrimary = self::choosePrimary($aAddresses, $sMetadataAddress);

        return [
            'primary'   => $sPrimary,
            'addresses' => self::primaryFirst($aAddresses, $sPrimary),
        ];
    }

    /**
     * @return array{addresses: list<array{address: string, scope: string, source: string}>}
     */
    public function jsonSerialize(): array
    {
        return $this->get();
    }

    /**
     * @return array{primary: string|null, addresses: list<array{address: string, scope: string, source: string}>}
     */
    private function snapshot(): array
    {
        if ($this->snapshot !== null) {
            return $this->snapshot;
        }

        return $this->snapshot = $this->collect();
    }

    /**
     * @return array{primary: string|null, addresses: list<array{address: string, scope: string, source: string}>}
     */
    private function collect(): array
    {
        switch (Os::getType()) {
            case Os::LINUX:
                $aInterfaces = self::parseInterfaceList(System::execString('hostname -I'));
                $sMetadata   = null;
                $sSource     = null;

                if (!self::hasPublicIpv4($aInterfaces)) {
                    $sCloud = self::cloudFromDmi(
                        self::dmiField('sys_vendor'),
                        self::dmiField('product_name')
                    );

                    if ($sCloud === 'gcp') {
                        $sMetadata = self::fetchGcpExternalIp();
                        $sSource   = 'gcp-metadata';
                    } elseif ($sCloud === 'ec2') {
                        $sMetadata = self::fetchEc2PublicIpv4();
                        $sSource   = 'ec2-metadata';
                    }
                }

                return self::select($aInterfaces, $sMetadata, $sSource);

            case Os::MACOS:
                return self::select([
                    System::execString('ipconfig getifaddr en0'),
                ]);
        }

        throw new HeartbeatException('Unable to determine IP address.');
    }

    /**
     * @param list<string> $aAddresses
     */
    private static function hasPublicIpv4(array $aAddresses): bool
    {
        foreach ($aAddresses as $sAddress) {
            if (self::isPublicIpv4($sAddress)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{address: string, scope: string, source: string}> $aAddresses
     * @param array<string, true>                                         $aSeen
     */
    private static function pushAddress(
        array &$aAddresses,
        array &$aSeen,
        string $sAddress,
        string $sSource
    ): void {
        $sAddress = trim($sAddress);
        $sScope   = self::scopeOf($sAddress);

        if ($sAddress === '' || $sScope === null || isset($aSeen[$sAddress])) {
            return;
        }

        $aSeen[$sAddress] = true;
        $aAddresses[]     = [
            'address' => $sAddress,
            'scope'   => $sScope,
            'source'  => $sSource,
        ];
    }

    /**
     * @param list<array{address: string, scope: string, source: string}> $aAddresses
     */
    private static function choosePrimary(array $aAddresses, ?string $sMetadataAddress): ?string
    {
        foreach ($aAddresses as $aEntry) {
            if ($aEntry['source'] === 'interface' && self::isPublicIpv4($aEntry['address'])) {
                return $aEntry['address'];
            }
        }

        if (is_string($sMetadataAddress) && self::isPublicIpv4($sMetadataAddress)) {
            return $sMetadataAddress;
        }

        foreach ($aAddresses as $aEntry) {
            if ($aEntry['source'] === 'interface' && filter_var($aEntry['address'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $aEntry['address'];
            }
        }

        foreach ($aAddresses as $aEntry) {
            if ($aEntry['source'] === 'interface') {
                return $aEntry['address'];
            }
        }

        return is_string($sMetadataAddress) && $sMetadataAddress !== '' ? $sMetadataAddress : null;
    }

    /**
     * @param list<array{address: string, scope: string, source: string}> $aAddresses
     *
     * @return list<array{address: string, scope: string, source: string}>
     */
    private static function primaryFirst(array $aAddresses, ?string $sPrimary): array
    {
        if ($sPrimary === null) {
            return $aAddresses;
        }

        $aHead = [];
        $aTail = [];

        foreach ($aAddresses as $aEntry) {
            if ($aEntry['address'] === $sPrimary && $aHead === []) {
                $aHead[] = $aEntry;
            } else {
                $aTail[] = $aEntry;
            }
        }

        return array_merge($aHead, $aTail);
    }

    private static function isCgnat(string $sAddress): bool
    {
        if (filter_var($sAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        $iLong = ip2long($sAddress);

        //  100.64.0.0/10
        return $iLong !== false && ($iLong & 0xFFC00000) === 0x64400000;
    }

    private static function dmiField(string $sField): string
    {
        $sPath = '/sys/class/dmi/id/' . $sField;

        if (!is_readable($sPath)) {
            return '';
        }

        $sValue = @file_get_contents($sPath);

        return is_string($sValue) ? trim($sValue) : '';
    }

    private static function fetchGcpExternalIp(): ?string
    {
        return self::validIp(self::http(
            'GET',
            'http://metadata.google.internal/computeMetadata/v1/instance/network-interfaces/0/access-configs/0/external-ip',
            ['Metadata-Flavor: Google']
        ));
    }

    private static function fetchEc2PublicIpv4(): ?string
    {
        $sToken = self::http(
            'PUT',
            'http://169.254.169.254/latest/api/token',
            ['X-aws-ec2-metadata-token-ttl-seconds: 21600']
        );

        if ($sToken === null) {
            return null;
        }

        return self::validIp(self::http(
            'GET',
            'http://169.254.169.254/latest/meta-data/public-ipv4',
            ['X-aws-ec2-metadata-token: ' . $sToken]
        ));
    }

    private static function validIp(?string $sAddress): ?string
    {
        if ($sAddress === null || filter_var($sAddress, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $sAddress;
    }

    /**
     * @param list<string> $aHeaders
     */
    private static function http(string $sMethod, string $sUrl, array $aHeaders = []): ?string
    {
        $rCurl = curl_init($sUrl);

        if ($rCurl === false) {
            return null;
        }

        curl_setopt_array($rCurl, [
            CURLOPT_CUSTOMREQUEST  => $sMethod,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::METADATA_TIMEOUT,
            CURLOPT_TIMEOUT        => self::METADATA_TIMEOUT,
            CURLOPT_HTTPHEADER     => $aHeaders,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $mBody = curl_exec($rCurl);
        $iCode = (int) curl_getinfo($rCurl, CURLINFO_HTTP_CODE);
        curl_close($rCurl);

        if (!is_string($mBody) || $iCode !== 200) {
            return null;
        }

        $sBody = trim($mBody);

        return $sBody === '' ? null : $sBody;
    }
}
