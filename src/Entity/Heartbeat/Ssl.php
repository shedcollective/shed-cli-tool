<?php

namespace Shed\Cli\Entity\Heartbeat;

use Shed\Cli\Exceptions\HeartbeatException;
use Shed\Cli\Helper\System;

/**
 * Class Ssl
 *
 * @package Shed\Cli\Entity\Heartbeat
 */
final class Ssl implements \JsonSerializable
{
    /**
     * Gathers details about SSL certificates
     *
     * @return array|null
     */
    public function get(): ?array
    {
        switch (Os::getType()) {
            case Os::LINUX:
                $certificates = $this->parseCertbotOutput(
                    $this->getCertbotCertificates()
                );
                break;

            case Os::MACOS:
                $certificates = null;
                break;

            default:
                throw new HeartbeatException('Unable to determine system load.');
        }
        return [
            'certificates' => $certificates,
        ];
    }

    public function jsonSerialize(): ?array
    {
        return $this->get();
    }

    function getCertbotCertificates(): array
    {
        $command    = 'sudo certbot certificates 2>&1';
        $output     = [];
        $resultCode = System::exec($command, $output);

        if ($resultCode !== 0) {
            throw new HeartbeatException('Failed to retrieve certificate information.');
        }

        return $output;
    }

    function parseCertbotOutput($output): array
    {
        $certificates = [];
        $currentCert  = null;

        foreach ($output as $line) {
            if (preg_match('/^Certificate Name: (.+)$/', $line, $matches)) {
                if ($currentCert !== null) {
                    $certificates[] = $currentCert;
                }
                $currentCert = ['name' => $matches[1]];

            } elseif (preg_match('/^\s*Domains: (.+)$/', $line, $matches)) {
                $currentCert['domains'] = explode(' ', $matches[1]);

            } elseif (preg_match('/^\s*Expiry Date: (.+) \(VALID:/', $line, $matches)) {
                $currentCert['expiry'] = $matches[1];
            }
        }

        if ($currentCert !== null) {
            $certificates[] = $currentCert;
        }

        return $certificates;
    }
}
