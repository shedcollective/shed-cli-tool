<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Helper\System;

/**
 * Class Hostname
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Hostname implements \JsonSerializable
{
    /**
     * Determines the server's domain
     *
     * @return string|null
     */
    public function get(): ?string
    {
        return System::execString('hostname');
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
