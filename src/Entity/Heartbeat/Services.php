<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Helper\System;

final class Services implements \JsonSerializable
{
    private const IMPORTANT_SERVICES = [
        'nginx',
        'apache2',
        'mysql',
        'postgresql',
        'php-fpm',
        'redis-server',
    ];

    public function get(): ?array
    {
        switch (Os::getType()) {
            case Os::LINUX:
                $services = [];
                foreach (self::IMPORTANT_SERVICES as $service) {
                    $status = System::execString("systemctl is-active $service 2>&1");
                    if ($status !== 'inactive') {
                        $services[$service] = [
                            'status' => $status,
                            'uptime' => $this->getServiceUptime($service),
                        ];
                    }
                }
                return $services;

            case Os::MACOS:
                return null; // macOS uses a different service management system

            default:
                throw new HeartbeatException('Unable to determine services status.');
        }
    }

    private function getServiceUptime(string $service): ?int
    {
        $pid = System::execString("systemctl show -p MainPID $service | cut -d'=' -f2");
        if ($pid && $pid !== '0') {
            $startTime = (int) System::execString("ps -o start_time= -p $pid");
            return $startTime ? time() - $startTime : null;
        }
        return null;
    }

    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
} 
