<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;

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
        //  @todo (Pablo 2021-08-18) - Complete this method
        return [
            'type'     => self::getType(),
            'codename' => '',
            'version'  => '',
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
