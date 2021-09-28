<?php

namespace Shed\Cli\Entity;

use Shed\Cli\Entity;

/**
 * Class Heartbeat
 *
 * @package Shed\Cli\Entity
 */
final class Heartbeat implements \JsonSerializable
{
    protected Entity\Heartbeat\Domain    $oDomain;
    protected Entity\Heartbeat\Ip        $oIp;
    protected Entity\Heartbeat\DiskUsage $oDiskUsage;
    protected Entity\Heartbeat\Load      $oLoad;

    // --------------------------------------------------------------------------

    /**
     * Heartbeat constructor.
     */
    public function __construct()
    {
        $this->oDomain    = new Entity\Heartbeat\Domain();
        $this->oIp        = new Entity\Heartbeat\Ip();
        $this->oDiskUsage = new Entity\Heartbeat\DiskUsage();
        $this->oLoad      = new Entity\Heartbeat\Load();
    }

    // --------------------------------------------------------------------------

    public function jsonSerialize(): array
    {
        return [
            'domain'     => $this->oDomain,
            'ip'         => $this->oIp,
            'disk_usage' => $this->oDiskUsage,
            'load'       => $this->oLoad,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Sends the collected data to the Shed server API
     */
    public function beat()
    {
        //  @todo (Pablo 2021-08-17) - complete this method
    }
}
