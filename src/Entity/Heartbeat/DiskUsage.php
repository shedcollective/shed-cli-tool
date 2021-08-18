<?php

namespace Shed\Cli\Entity\Heartbeat;

/**
 * Class DiskUsage
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class DiskUsage implements \JsonSerializable
{
    /**
     * Determines the server's available disk space
     *
     * @return array|null
     */
    public function get(): ?array
    {
        //  @todo (Pablo 2021-08-18) - Complete this method
        return [
            'size'      => 0,
            'available' => 0,
        ];
    }

    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
}
