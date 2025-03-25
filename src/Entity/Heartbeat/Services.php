<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;

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
                    $status = exec("systemctl is-active $service 2>&1");
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
        $pid = exec("systemctl show -p MainPID $service | cut -d'=' -f2");
        if ($pid && $pid !== '0') {
            $startTime = (int) exec("ps -o start_time= -p $pid");
            return $startTime ? time() - $startTime : null;
        }
        return null;
    }

    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
} 