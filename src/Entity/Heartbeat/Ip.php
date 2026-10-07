<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;

/**
 * Class Ip
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Ip implements \JsonSerializable
{
    public function __construct(private IpAddresses $oAddresses)
    {
    }

    /**
     * The server's primary IP — a public IPv4 when one is known
     *
     * Collected alongside {@see IpAddresses} so `hostname -I` and any cloud
     * metadata probe run once per heartbeat.
     *
     * @return string
     */
    public function get(): string
    {
        $sPrimary = $this->oAddresses->primary();

        if ($sPrimary === null || $sPrimary === '') {
            throw new HeartbeatException('Unable to determine IP address.');
        }

        return $sPrimary;
    }

    // --------------------------------------------------------------------------

    /**
     * @return string
     */
    public function jsonSerialize(): string
    {
        return $this->get();
    }
}
