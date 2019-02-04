<?php

namespace Shed\Cli\Server\Infrastructure;

use Shed\Cli\Interfaces\Infrastructure;

final class DigitalOcean extends Base implements Infrastructure
{
    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getName(): string
    {
        return 'DigitalOcean';
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
     */
    public function create(): void
    {
    }

    // --------------------------------------------------------------------------

    /**
     * Destroy the server
     */
    public function destroy(): void
    {
    }
}
