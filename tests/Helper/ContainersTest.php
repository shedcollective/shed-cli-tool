<?php

namespace Shed\Cli\Tests\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shed\Cli\Helper\Containers;

final class ContainersTest extends TestCase
{
    private const ID = '1ec73de80a361b3ad9fe5576ce0746260d32d376ef43f5e7d7ea6a43f4dc189c';

    protected function tearDown(): void
    {
        Containers::reset();
    }

    /**
     * @return array<string, array{string, array{id: string, runtime: string}|null}>
     */
    public static function cgroups(): array
    {
        $sId = self::ID;

        return [
            'cgroup v2 under systemd' => [
                "0::/system.slice/docker-{$sId}.scope\n",
                ['id' => $sId, 'runtime' => 'docker'],
            ],
            'cgroup v1' => [
                "12:pids:/docker/{$sId}\n11:memory:/docker/{$sId}\n0::/docker/{$sId}\n",
                ['id' => $sId, 'runtime' => 'docker'],
            ],
            'containerd under Kubernetes' => [
                "0::/kubepods.slice/kubepods-burstable.slice/kubepods-burstable-pod1.slice/cri-containerd-{$sId}.scope\n",
                ['id' => $sId, 'runtime' => 'containerd'],
            ],
            'podman' => [
                "0::/machine.slice/libpod-{$sId}.scope/container\n",
                ['id' => $sId, 'runtime' => 'podman'],
            ],
            'a host process' => [
                "0::/user.slice/user-1000.slice/session-3.scope\n",
                null,
            ],
            'a host service' => [
                "0::/system.slice/docker.service\n",
                null,
            ],
            'pid 1 on the host' => [
                "0::/init.scope\n",
                null,
            ],
        ];
    }

    #[DataProvider('cgroups')]
    public function test_it_finds_the_container_in_a_cgroup(string $cgroup, ?array $expected): void
    {
        $this->assertSame($expected, Containers::parseCgroup($cgroup));
    }

    public function test_it_falls_back_to_the_mounts_docker_bind_mounts_in(): void
    {
        $sId     = self::ID;
        $sMounts = "1236 1210 0:110 / / rw,relatime master:571 - overlay overlay rw\n"
            . "1250 1236 254:1 /var/lib/docker/containers/{$sId}/hostname /etc/hostname rw,relatime - ext4 /dev/vda1 rw\n";

        $this->assertSame(['id' => $sId, 'runtime' => 'docker'], Containers::parseMountinfo($sMounts));
        $this->assertNull(Containers::parseMountinfo("22 1 254:1 / / rw,relatime shared:1 - ext4 /dev/vda1 rw\n"));
    }

    public function test_it_parses_an_inspect_line(): void
    {
        $sLine = implode("\t", [
            json_encode(self::ID),
            '"/www-backend-1"',
            '"storysloth-backend:latest"',
            '"sha256:93aa3f61216b73cc6f878235c9772849449f4efc18dfe3d6678eed645076c00d"',
            '"2026-10-30T22:10:01.123456789Z"',
            '"2026-10-31T03:30:45.987654321Z"',
            '"running"',
            '2',
            '"www"',
            '"backend"',
        ]);

        $this->assertSame([
            'id'            => self::ID,
            'name'          => 'www-backend-1',
            'image'         => 'storysloth-backend:latest',
            'image_id'      => 'sha256:93aa3f61216b73cc6f878235c9772849449f4efc18dfe3d6678eed645076c00d',
            'created'       => '2026-10-30T22:10:01Z',
            'started_at'    => '2026-10-31T03:30:45Z',
            'status'        => 'running',
            'restart_count' => 2,
            'compose'       => ['project' => 'www', 'service' => 'backend'],
        ], Containers::parseInspectLine($sLine . "\n"));
    }

    public function test_a_container_outside_compose_has_no_compose_labels(): void
    {
        $sLine = implode("\t", [json_encode(self::ID), '"/redis"', '"redis:7"', '"sha256:abc"', '"2026-10-30T22:10:01Z"', '"0001-01-01T00:00:00Z"', '"created"', '0', '""', '""']);

        $aContainer = Containers::parseInspectLine($sLine);

        $this->assertNotNull($aContainer);
        $this->assertNull($aContainer['compose']);
        $this->assertNull($aContainer['started_at'], "Docker's zero time is never-started, not year one");
    }

    public function test_it_rejects_an_inspect_line_it_did_not_ask_for(): void
    {
        $this->assertNull(Containers::parseInspectLine('Error: No such object: abc'));
        $this->assertNull(Containers::parseInspectLine(implode("\t", array_fill(0, 10, '"x"'))));
    }

    public function test_it_reads_a_file_out_of_a_docker_cp_tar_stream(): void
    {
        $sBody   = "ID=debian\nVERSION_ID=\"12\"\n";
        $sHeader = str_pad('os-release', 100, "\0")
            . str_pad('0000644', 8, "\0")
            . str_pad('0000000', 8, "\0")
            . str_pad('0000000', 8, "\0")
            . str_pad(sprintf('%011o', strlen($sBody)), 12, "\0")
            . str_pad('', 12, "\0")
            . str_repeat(' ', 8)
            . '0';
        $sTar    = str_pad($sHeader, 512, "\0") . str_pad($sBody, 512, "\0");

        $this->assertSame($sBody, Containers::untar($sTar, 4096));
        $this->assertSame('ID=deb', Containers::untar($sTar, 6));
        $this->assertNull(Containers::untar('Error response from daemon: Could not find the file', 4096));
    }

    public function test_each_pass_has_its_own_budget(): void
    {
        $this->assertTrue(Containers::withinBudget(Containers::BUDGET_PROBES));

        $oDeadlines = new \ReflectionProperty(Containers::class, 'aDeadlines');
        $oDeadlines->setValue(null, [Containers::BUDGET_PROBES => 0.0]);

        $this->assertFalse(Containers::withinBudget(Containers::BUDGET_PROBES));
        $this->assertTrue(
            Containers::withinBudget(Containers::BUDGET_PROCESSES),
            'A slow probe pass must not cost the node processes their versions'
        );
    }
}
