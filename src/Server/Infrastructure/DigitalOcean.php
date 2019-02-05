<?php

namespace Shed\Cli\Server\Infrastructure;

use Shed\Cli\Command\DigitalOcean\Auth;
use Shed\Cli\Exceptions\CliException;
use Shed\Cli\Interfaces\Infrastructure;
use Shed\Cli\Helper\Config;
use Shed\Cli\Resources\Option;

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
        return [
            'CONTEXT' => new Option(
                Option::TYPE_CHOOSE,
                'DO Account',
                null,
                function () {
                    $oAccounts = Auth::getAccounts();
                    if (empty($oAccounts)) {
                        throw new CliException(
                            'No Digital Ocean accounts configured; `shed digitalocean:auth` to configure'
                        );
                    }
                    return array_keys((array) $oAccounts);
                }
            ),
        ];
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
