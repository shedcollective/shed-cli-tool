<?php

namespace Shed\Cli\Entity\Heartbeat;

/**
 * Class Load
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Load implements \JsonSerializable
{
    /**
     * Determines the server's load
     *
     * @return array|null
     */
    public function get(): ?array
    {
        //  @todo (Pablo 2021-08-18) - Complete this method
        return [
            '5_min'  => 0,
            '10_min' => 0,
            '15_min' => 0,
        ];
    }

    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
}
