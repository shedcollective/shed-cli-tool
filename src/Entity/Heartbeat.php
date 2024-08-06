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
    protected Entity\Heartbeat\Hostname  $oHostname;
    protected Entity\Heartbeat\Ip        $oIp;
    protected Entity\Heartbeat\DiskUsage $oDiskUsage;
    protected Entity\Heartbeat\Load      $oLoad;
    protected Entity\Heartbeat\Apt       $oApt;
    protected Entity\Heartbeat\Os        $oOs;
    protected Entity\Heartbeat\Ssl       $oSsl;

    // --------------------------------------------------------------------------

    /**
     * Heartbeat constructor.
     */
    public function __construct()
    {
        $this->oHostname  = new Entity\Heartbeat\Hostname();
        $this->oIp        = new Entity\Heartbeat\Ip();
        $this->oDiskUsage = new Entity\Heartbeat\DiskUsage();
        $this->oLoad      = new Entity\Heartbeat\Load();
        $this->oApt       = new Entity\Heartbeat\Apt();
        $this->oOs        = new Entity\Heartbeat\Os();
        $this->oSsl       = new Entity\Heartbeat\Ssl();
    }

    // --------------------------------------------------------------------------

    public function jsonSerialize(): array
    {
        return $this->get();
    }

    // --------------------------------------------------------------------------

    public function get(): array
    {
        return [
            'hostname' => $this->oHostname,
            'ip'       => $this->oIp,
            'disk'     => $this->oDiskUsage,
            'load'     => $this->oLoad,
            'apt'      => $this->oApt,
            'os'       => $this->oOs,
            'ssl'      => $this->oSsl,
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
