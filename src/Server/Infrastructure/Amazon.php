<?php

namespace Shed\Cli\Server\Infrastructure;

use Shed\Cli\Interfaces\Infrastructure;
use Shed\Cli\Resources\Server;

final class Amazon extends Base implements Infrastructure
{
    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Amazon Web Services';
    }

    // --------------------------------------------------------------------------

    /**
     * The configurable options for the framework
     *
     * @return array
     */
    public function getOptions(): array
    {
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Returns any ENV vars for the project
     *
     * @return array
     */
    public function getEnvVars(): array
    {
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Create the server
     *
     * @param string $sDomain  The configured domain name
     * @param array  $aOptions The configured options
     *
     * @return Server
     */
    public function create(string $sDomain, array $aOptions): Server
    {
        $this->oOutput->writeln('');
        $this->oOutput->writeln('🚧 Deploying AWS servers is a work in progress');
        $this->oOutput->writeln('');
    }

    // --------------------------------------------------------------------------

    /**
     * Destroy the server
     */
    public function destroy(): void
    {
    }
}
