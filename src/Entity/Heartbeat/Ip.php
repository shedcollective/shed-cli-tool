<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;

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
        switch (Os::getType()) {
            case Os::LINUX:
                return exec('hostname -I | awk \'{print $1}\'');
            case Os::MACOS:
                return exec('ipconfig getifaddr en0');
        }

        throw new HeartbeatException('Unable to determine IP address.');
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
