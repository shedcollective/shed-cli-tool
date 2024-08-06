<?php

namespace Shed\Cli\Entity\Heartbeat;

/**
 * Class Apt
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Apt implements \JsonSerializable
{
    /**
     * Gathers details about apt packages
     *
     * @return array|null
     */
    public function get(): ?array
    {
        //  @todo (Pablo 2021-08-18) - Complete this method
        return [
            'updates' => 0,
            'restart' => false,
        ];
    }

    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
}
