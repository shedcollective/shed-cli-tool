<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;

/**
 * Class Apt
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Apt implements \JsonSerializable
{
    /**
     * Gathers details about apt packages
     *
     * @return array|null
     */
    public function get(): ?array
    {
        switch (Os::getType()) {
            case Os::LINUX:
                $updates = trim(exec(
                    <<<EOT
                    /usr/lib/update-notifier/apt-check 2>&1
                    EOT
                ));
                $updates = explode(";", $updates);
                $updates = reset($updates);
                break;

            case Os::MACOS;
                return null;

            default:
                throw new HeartbeatException('Unable to determine system apt status.');
        }

        return [
            'updates' => $updates,
        ];
    }

    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
}
