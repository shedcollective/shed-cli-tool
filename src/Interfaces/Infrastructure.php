<?php

namespace Shed\Cli\Interfaces;

interface Infrastructure
{
    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getName(): string;

    // --------------------------------------------------------------------------

    /**
     * The configurable options for the framework
     *
     * @return array
     */
    public function getOptions(): array;

    // --------------------------------------------------------------------------

    /**
     * Returns any ENV vars for the project
     *
     * @return array
     */
    public function getEnvVars(): array;

    // --------------------------------------------------------------------------

    /**
     * Create the server
     *
     * @param string $sDomain  The configured domain name
     * @param array  $aOptions The configured options
     */
    public function create(string $sDomain, array $aOptions): void;

    // --------------------------------------------------------------------------

    /**
     * Destroy the server
     */
    public function destroy(): void;
}
