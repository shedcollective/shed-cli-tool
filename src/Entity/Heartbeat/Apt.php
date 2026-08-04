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
     * Reports the number of pending updates. Ships in update-notifier-common,
     * which is an Ubuntu package - it is absent on plain Debian.
     *
     * @var string
     */
    private const APT_CHECK = '/usr/lib/update-notifier/apt-check';

    // --------------------------------------------------------------------------

    /**
     * Gathers details about apt packages
     *
     * @return array|null
     */
    public function get(): ?array
    {
        switch (Os::getType()) {
            case Os::LINUX:
                [$updates, $security] = $this->getUpdateCounts();
                break;

            case Os::MACOS:
                return null;

            default:
                throw new HeartbeatException('Unable to determine system apt status.');
        }

        return [
            'updates'          => $updates,
            'security_updates' => $security,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Reads the pending update counts from apt-check
     *
     * apt-check writes `<updates>;<security updates>` to stderr, hence the
     * redirect. The second field matters as much as the first: the default
     * Allowed-Origins configuration only upgrades security origins, so it is the
     * security count which says whether unattended-upgrades is keeping pace.
     *
     * Both are null where the counts cannot be established. Reporting zero would
     * be indistinguishable from a fully patched host, turning an absent
     * apt-check into a false all-clear.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function getUpdateCounts(): array
    {
        //  An absolute path outside $PATH, so tested directly rather than via
        //  System::commandExists(), which shells out to `which`
        if (!is_executable(self::APT_CHECK)) {
            return [null, null];
        }

        $output = trim(exec(escapeshellarg(self::APT_CHECK) . ' 2>&1') ?: '');
        $parts  = explode(';', $output);

        if (count($parts) < 2 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
            return [null, null];
        }

        return [(int) $parts[0], (int) $parts[1]];
    }

    // --------------------------------------------------------------------------

    public function jsonSerialize(): ?array
    {
        return $this->get();
    }
}
