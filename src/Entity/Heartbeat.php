<?php

namespace Shed\Cli\Entity;

use Shed\Cli\Entity;
use Shed\Cli\Exceptions\HeartbeatException;

/**
 * Class Heartbeat
 *
 * @package Shed\Cli\Entity
 */
final class Heartbeat implements \JsonSerializable
{
    protected Entity\Heartbeat\Apt       $oApt;
    protected Entity\Heartbeat\DiskUsage $oDiskUsage;
    protected Entity\Heartbeat\Hostname  $oHostname;
    protected Entity\Heartbeat\Ip        $oIp;
    protected Entity\Heartbeat\Load      $oLoad;
    protected Entity\Heartbeat\Memory    $oMemory;
    protected Entity\Heartbeat\Os        $oOs;
    protected Entity\Heartbeat\PhpInfo   $oPhpInfo;
    protected Entity\Heartbeat\Security  $oSecurity;
    protected Entity\Heartbeat\Services  $oServices;
    protected Entity\Heartbeat\Ssl       $oSsl;

    // --------------------------------------------------------------------------

    /**
     * Heartbeat constructor.
     */
    public function __construct()
    {
        $this->oApt       = new Entity\Heartbeat\Apt();
        $this->oDiskUsage = new Entity\Heartbeat\DiskUsage();
        $this->oHostname  = new Entity\Heartbeat\Hostname();
        $this->oIp        = new Entity\Heartbeat\Ip();
        $this->oLoad      = new Entity\Heartbeat\Load();
        $this->oMemory    = new Entity\Heartbeat\Memory();
        $this->oOs        = new Entity\Heartbeat\Os();
        $this->oPhpInfo   = new Entity\Heartbeat\PhpInfo();
        $this->oSecurity  = new Entity\Heartbeat\Security();
        $this->oServices  = new Entity\Heartbeat\Services();
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
            'apt'      => $this->oApt,
            'disk'     => $this->oDiskUsage,
            'hostname' => $this->oHostname,
            'ip'       => $this->oIp,
            'load'     => $this->oLoad,
            'memory'   => $this->oMemory,
            'os'       => $this->oOs,
            'php'      => $this->oPhpInfo,
            'security' => $this->oSecurity,
            'services' => $this->oServices,
            'ssl'      => $this->oSsl,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Sends the collected data to the Shed server API
     *
     * @throws HeartbeatException If the API request fails
     */
    public function beat(): void
    {
        $endpoint = 'https://api.shedcollective.com/server/heartbeat';
        $data     = json_encode($this);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            throw new HeartbeatException('Failed to send heartbeat: ' . curl_error($ch));
        }

        if ($httpCode !== 200) {
            throw new HeartbeatException('Heartbeat API returned error code: ' . $httpCode);
        }

        curl_close($ch);
    }
}
