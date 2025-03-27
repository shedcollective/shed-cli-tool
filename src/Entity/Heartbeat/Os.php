<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Helper\System;

/**
 * Class Os
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Os implements \JsonSerializable
{
    const LINUX   = 'linux';
    const MACOS   = 'macos';
    const WINDOWS = 'windows';

    /**
     * Gathers details about the OS
     *
     * @return array|null
     */
    public function get(): ?array
    {
        switch (static::getType()) {
            case self::LINUX:
                $version         = System::execString('. /etc/os-release && echo $VERSION_ID');
                $codename        = System::execString('. /etc/os-release && echo $VERSION_CODENAME');
                $restartRequired = file_exists('/var/run/reboot-required');
                break;

            case self::MACOS:
                $version         = System::execString('sw_vers -productVersion');
                $codename        = System::execString('sw_vers -productName');
                $restartRequired = null;
                break;

            default:
                throw new HeartbeatException('Unable to determine OS Details.');
        }

        return [
            'type'             => self::getType(),
            'codename'         => $codename,
            'version'          => $version,
            'restart_required' => $restartRequired,
        ];
    }

    public static function getType(): string
    {
        if (exec('uname') === 'Darwin') {
            return self::MACOS;
        } elseif (exec('expr substr $(uname -s) 1 5') === 'Linux') {
            return self::LINUX;
        } elseif (exec('expr substr $(uname -s) 1 10') === 'MINGW32_NT') {
            return self::WINDOWS;
        } elseif (exec('expr substr $(uname -s) 1 10') === 'MINGW64_NT') {
            return self::WINDOWS;
        }

        throw new HeartbeatException('Unable to determine OS');
    }

    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
}
