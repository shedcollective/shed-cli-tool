<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Helper\Updates;

/**
 * Class Version
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Version implements \JsonSerializable
{
    /**
     * Determines the version of the Shed CLI tool which sent the heartbeat
     *
     * @return string|null
     */
    public function get(): ?string
    {
        return Updates::getCurrentVersion();
    }

    // --------------------------------------------------------------------------

    /**
     * @return string|null
     */
    public function jsonSerialize(): ?string
    {
        return $this->get();
    }
}
