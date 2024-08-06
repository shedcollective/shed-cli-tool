<?php

namespace Shed\Cli\Entity\Heartbeat;

/**
 * Class Ssl
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Ssl implements \JsonSerializable
{
    /**
     * Gathers details about SSL certificates
     *
     * @return array|null
     */
    public function get(): ?array
    {
        //  @todo (Pablo 2021-08-18) - Complete this method
        return [
            'certificates' => [
                [
                    'domain'  => '',
                    'expires' => '',
                ],
            ],
        ];
    }

    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
}
