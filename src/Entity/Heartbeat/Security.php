<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Helper\System;

final class Security implements \JsonSerializable
{
    public function get(): ?array
    {
        switch (Os::getType()) {
            case Os::LINUX:
                return [
                    'failed_logins' => $this->getFailedLogins(),
                    'last_login'    => $this->getLastLogin(),
                    'open_ports'    => $this->getOpenPorts(),
                ];

            case Os::MACOS:
                return null; // Most of these metrics are Linux-specific

            default:
                throw new HeartbeatException('Unable to determine security status.');
        }
    }

    private function getFailedLogins(): ?array
    {
        $output = [];
        exec('grep "Failed password" /var/log/auth.log | wc -l', $output);
        $totalFailed = (int) ($output[0] ?? 0);

        $recentOutput = [];
        System::exec('grep "Failed password" /var/log/auth.log | tail -n 5', $recentOutput);

        return [
            'total_count'     => $totalFailed,
            'recent_attempts' => $recentOutput,
        ];
    }

    private function getLastLogin(): ?string
    {
        return System::execString('last -1 -F | head -1') ?: null;
    }

    private function getOpenPorts(): array
    {
        $output = [];
        System::exec("ss -tuln | grep LISTEN | awk '{print $5}' | cut -d: -f2", $output);
        return array_filter(array_map('trim', $output));
    }

    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
} 
