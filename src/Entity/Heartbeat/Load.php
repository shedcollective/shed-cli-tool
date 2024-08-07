<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;

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
        switch (Os::getType()) {
            case Os::LINUX:
                $load = trim(exec('uptime | awk -F\'load average:\' \'{ print $2 }\' | awk \'{ print $1 $2 $3 }\''));
                [$min5, $min10, $min15] = explode(',', $load);
                break;

            case Os::MACOS:
                $load = trim(exec('uptime | awk -F\'load averages:\' \'{ print $2 }\' | awk \'{ print $1,$2,$3 }\''));
                [$min5, $min10, $min15] = explode(' ', $load);
                break;

            default:
                throw new HeartbeatException('Unable to determine system load.');
        }

        return [
            '5_min'  => $min5,
            '10_min' => $min10,
            '15_min' => $min15,
        ];
    }

    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
}
