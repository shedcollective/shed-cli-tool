<?php

namespace Shed\Cli\Command\Auth;

use Shed\Cli\Command\Auth;
use Shed\Cli\Server\Provider\Api;

final class DigitalOcean extends Auth
{
    /**
     * The third party slug
     *
     * @var string
     */
    const SLUG = 'digitalocean';

    /**
     * The third party name
     *
     * @var string
     */
    const LABEL = 'Digital Ocean';

    // --------------------------------------------------------------------------

    /**
     * Show help on how to generate credentials
     */
    protected function help(): void
    {
        $this->oOutput->writeln('');
        $this->oOutput->writeln('To generate a new Personal Access Token:');
        $this->oOutput->writeln('');
        $this->oOutput->writeln('<comment>1:</comment> Generate a Personal Access Token here: <comment>https://cloud.digitalocean.com/account/api/tokens</comment>');
        $this->oOutput->writeln('<comment>2:</comment> Run: <comment>shed auth:digitalocean</comment>');
        $this->oOutput->writeln('<comment>3:</comment> Specify a label for the account');
        $this->oOutput->writeln('<comment>4:</comment> Specify the access token');
        $this->oOutput->writeln('');
    }

    // --------------------------------------------------------------------------

    /**
     * Verify a token is valid
     *
     * @param string $sToken The token to validate
     */
    protected function testToken(string $sToken): void
    {
        Api\DigitalOcean::test($sToken);
    }
}
