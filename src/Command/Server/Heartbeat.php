<?php

namespace Shed\Cli\Command\Server;

use Shed\Cli\Command;
use Shed\Cli\Entity;

/**
 * Class Heartbeat
 *
 * @package Shed\Cli\Command\Server
 */
final class Heartbeat extends Command
{
    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('server:heartbeat')
            ->setDescription('Sends a server heartbeat')
            ->setHelp('This command gathers system information and reports it to the Shed server API.');
    }

    // --------------------------------------------------------------------------

    /**
     * Seconds to wait before collecting, derived from the hostname so each host
     * lands in a stable slot across the ten minutes after the hour. Spreads the
     * fleet's :00 cron burst. 0–599 inclusive.
     */
    public static function jitterSeconds(string $hostname): int
    {
        return (int) (sprintf('%u', crc32($hostname)) % 600);
    }

    /**
     * Execute the command
     *
     * @return int
     */
    protected function go(): int
    {
        $this->banner('Heartbeat');

        // Cron is non-interactive; skip the wait when someone runs this by hand.
        if (!$this->oInput->isInteractive()) {
            sleep(self::jitterSeconds(gethostname() ?: ''));
        }

        try {
            $oHeartbeat = new Entity\Heartbeat();
            $oHeartbeat->beat();

            $this->oOutput->writeln('Heartbeat successful');
            $this->oOutput->writeln('');

            return self::EXIT_CODE_SUCCESS;

        } catch (\Throwable $e) {
            $this->oOutput->writeln(sprintf(
                '<error>Error [%s]: %s</error>',
                $e->getCode(),
                $e->getMessage()
            ));

            return self::EXIT_CODE_ERROR;
        }
    }
}
