<?php

namespace Shed\Cli\Command\Server;

use Shed\Cli\Command;
use Shed\Cli\Entity;
use Symfony\Component\Console\Input\InputOption;

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
            ->setHelp('This command gathers system information and reports it to the Shed server API.')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Print the payload instead of sending it'
            );
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
     * Whether to wait before collecting.
     *
     * Symfony Console 6 leaves isInteractive() true unless --no-interaction or
     * --quiet is passed, and the hourly cron line passes neither. A missing
     * terminal is the unattended case; those flags still wait at a terminal.
     */
    public static function shouldWait(bool $interactive, bool $stdinIsTerminal): bool
    {
        return !$interactive || !$stdinIsTerminal;
    }

    /**
     * Execute the command
     *
     * @return int
     */
    protected function go(): int
    {
        $this->banner('Heartbeat');

        $bDryRun = (bool) $this->oInput->getOption('dry-run');

        // Cron has no terminal. Skip the wait when someone runs this by hand, and on a dry run,
        // which is always by hand.
        if (!$bDryRun && self::shouldWait($this->oInput->isInteractive(), self::stdinIsTerminal())) {
            sleep(self::jitterSeconds(gethostname() ?: ''));
        }

        try {
            $oHeartbeat = new Entity\Heartbeat();

            if ($bDryRun) {
                $this->oOutput->writeln((string) json_encode($oHeartbeat, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return self::EXIT_CODE_SUCCESS;
            }

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

    /**
     * True when stdin is a terminal. stream_isatty is in core since PHP 7.2,
     * so this does not depend on the posix extension.
     */
    private static function stdinIsTerminal(): bool
    {
        return \defined('STDIN') && \is_resource(\STDIN) && @\stream_isatty(\STDIN);
    }
}
