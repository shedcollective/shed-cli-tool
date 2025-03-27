<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Helper\System;

final class Memory implements \JsonSerializable
{
    public function get(): ?array
    {
        switch (Os::getType()) {
            case Os::LINUX:
                $meminfo = file_get_contents('/proc/meminfo');
                preg_match_all('/^(\w+):\s+(\d+)/m', $meminfo, $matches, PREG_SET_ORDER);
                $memory = [];
                foreach ($matches as $match) {
                    $memory[$match[1]] = (int) $match[2] * 1024; // Convert from KB to bytes
                }

                return [
                    'total'      => $memory['MemTotal'] ?? null,
                    'free'       => $memory['MemFree'] ?? null,
                    'available'  => $memory['MemAvailable'] ?? null,
                    'swap_total' => $memory['SwapTotal'] ?? null,
                    'swap_free'  => $memory['SwapFree'] ?? null,
                ];

            case Os::MACOS:
                $pageSize = (int) System::execString('sysctl -n hw.pagesize');
                $totalMem = (int) System::execString('sysctl -n hw.memsize');
                $vmStats  = explode("\n", System::execString('vm_stat'));

                $stats = [];
                foreach ($vmStats as $row) {
                    if (preg_match('/^(.+):\s+(\d+)/', $row, $matches)) {
                        $stats[$matches[1]] = (int) $matches[2] * $pageSize;
                    }
                }

                return [
                    'total'      => $totalMem,
                    'free'       => $stats['Pages free'] ?? null,
                    'available'  => null, // macOS doesn't provide this directly
                    'swap_total' => null, // Would need additional commands to get swap info
                    'swap_free'  => null,
                ];

            default:
                throw new HeartbeatException('Unable to determine memory usage.');
        }
    }

    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
} 
