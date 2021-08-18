<?php

namespace Shed\Cli\Entity\Heartbeat;

/**
 * Class Domain
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Domain implements \JsonSerializable
{
    /**
     * Determines the server's domain
     *
     * @return string|null
     */
    public function get(): ?string
    {
        //  @todo (Pablo 2021-08-18) - Complete this method
        return 'test.com';
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
