<?php

namespace Shed\Cli\Tests\Command\Server;

use PHPUnit\Framework\TestCase;
use Shed\Cli\Command\Server\Heartbeat;

final class HeartbeatTest extends TestCase
{
    public function test_jitter_is_in_range(): void
    {
        $offset = Heartbeat::jitterSeconds('web-01.example');

        $this->assertGreaterThanOrEqual(0, $offset);
        $this->assertLessThan(600, $offset);
    }

    public function test_jitter_is_stable_for_a_hostname(): void
    {
        $hostname = 'web-01.example';

        $this->assertSame(
            Heartbeat::jitterSeconds($hostname),
            Heartbeat::jitterSeconds($hostname)
        );
    }

    public function test_different_hostnames_land_in_different_slots(): void
    {
        $this->assertNotSame(
            Heartbeat::jitterSeconds('alpha.example'),
            Heartbeat::jitterSeconds('beta.example')
        );
    }
}
