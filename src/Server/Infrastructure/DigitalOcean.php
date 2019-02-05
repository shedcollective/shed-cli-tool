<?php

namespace Shed\Cli\Server\Infrastructure;

use Shed\Cli\Command\DigitalOcean\Auth;
use Shed\Cli\Exceptions\CliException;
use Shed\Cli\Interfaces\Infrastructure;
use Shed\Cli\Helper\Config;

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
            'CONTEXT' => (object) [
                'type'    => 'choose',
                'label'   => 'Which DigitalOcean Account',
                'options' => function () {
                    $aAccounts = array_map(function ($sItem) {
                        $aToken = explode(Auth::CONFIG_ACCOUNTS_SEPARATOR, $sItem);
                        return reset($aToken);
                    }, Config::get(Auth::CONFIG_ACCOUNTS_KEY));

                    if (empty($aAccounts)) {
                        throw new CliException(
                            'No Digital Ocean accounts configured; `shed digitalocean:auth` to configure'
                        );
                    }
                    return $aAccounts;
                },
            ],
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
