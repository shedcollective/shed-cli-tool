<?php

namespace Shed\Cli\Entity\Heartbeat;

/**
 * Class Ip
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Ip implements \JsonSerializable
{
    /**
     * Determines the server's IP address
     *
     * @return string|null
     */
    public function get(): ?string
    {
        //  @todo (Pablo 2021-08-18) - Complete this method
        return '0.0.0.0';
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
