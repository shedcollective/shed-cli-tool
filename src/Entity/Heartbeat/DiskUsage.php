<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Helper\System;

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
        switch (Os::getType()) {
            case Os::LINUX:
                $usage = System::execString('df --block-size=1 --output=size,used,avail / | tail -n 1');
                break;

            case Os::MACOS:
                $usage = System::execString('df -k / | tail -n 1 | awk \'{print $2*1024, $3*1024, $4*1024}\'');
                break;

            default:
                throw new HeartbeatException('Unable to determine disk usage.');
        }

        [$size, $used, $available] = explode(' ', $usage);

        return [
            'size'      => (int) $size,
            'used'      => (int) $used,
            'available' => (int) $available,
        ];
    }

    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
}
