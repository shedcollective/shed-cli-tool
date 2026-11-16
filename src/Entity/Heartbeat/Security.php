<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Exceptions\System\CommandFailedException;
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
        if (!is_readable('/var/log/auth.log')) {
            return [
                'total_count'     => null,
                'recent_attempts' => null,
                'error'           => 'Access to auth.log denied - insufficient permissions',
            ];
        }

        $totalFailed = (int) System::execString('grep "Failed password" /var/log/auth.log | wc -l');

        $recentOutput = [];
        System::exec('grep "Failed password" /var/log/auth.log | tail -n 5', $recentOutput);

        return [
            'total_count'     => $totalFailed,
            'recent_attempts' => $recentOutput,
        ];
    }

    /**
     * The most recent wtmp record, or null when it cannot be read.
     *
     * Ubuntu 26.04 no longer ships util-linux's last. The wtmpdb package
     * provides /usr/bin/last (a symlink to wtmpdb) and accepts the same
     * -1 and -F flags. A host without that package has no last binary;
     * spawning it prints "last: not found" on the shell's stderr, which
     * PHP does not capture, and the pipeline still exits 0 because of
     * head. Skip the command instead, and treat an empty database the
     * same as a missing one.
     */
    private function getLastLogin(): ?string
    {
        if (!System::commandExists('last')) {
            return null;
        }

        try {
            $line = System::execString('last -1 -F 2>/dev/null | head -1');
        } catch (CommandFailedException) {
            return null;
        }

        return self::parseLastLogin($line);
    }

    /**
     * Keeps a login line and drops the footer last prints when the
     * database is empty ("wtmpdb has no entries", "wtmp begins …").
     */
    public static function parseLastLogin(?string $line): ?string
    {
        $line = trim((string) $line);
        if ($line === '' || str_contains($line, 'has no entries')) {
            return null;
        }

        if (preg_match('/^(?:wtmp|wtmpdb) begins /', $line) === 1) {
            return null;
        }

        return $line;
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
