<?php

namespace Shed\Cli\Command\Auth;

use Shed\Cli\Command\Auth;
use Shed\Cli\Service\ShedApi;

final class Shed extends Auth
{
    /**
     * The third party slug
     *
     * @var string
     */
    const SLUG = 'shed';

    /**
     * The third party name
     *
     * @var string
     */
    const LABEL = 'Shed';

    // --------------------------------------------------------------------------

    /**
     * Show help on how to generate credentials
     */
    protected function help(): void
    {
        $this->oOutput->writeln('');
        $this->oOutput->writeln('To generate a new Personal Access Token:');
        $this->oOutput->writeln('');
        $this->oOutput->writeln('<comment>1:</comment> Get your Personal Access Token here: <info>https://shedcollective.com/dashboard/servers/api</info>');
        $this->oOutput->writeln('<comment>2:</comment> Run: <info>shed auth:shed</info>');
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
        ShedApi::testToken($sToken);
    }
}
