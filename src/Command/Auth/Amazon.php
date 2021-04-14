<?php

namespace Shed\Cli\Command\Auth;

use Shed\Cli\Command\Auth;
use Shed\Cli\Server\Provider\Api;

final class Amazon extends Auth
{
    /**
     * The third party slug
     *
     * @var string
     */
    const SLUG = 'amazon';

    /**
     * The third party name
     *
     * @var string
     */
    const LABEL = 'Amazon';

    /**
     * The question for asking the account label
     *
     * @var string
     */
    const QUESTION_LABEL = 'Access Key';

    /**
     * The question for asking the account token
     *
     * @var string
     */
    const QUESTION_TOKEN = 'Access Secret';

    // --------------------------------------------------------------------------

    /**
     * Show help on how to generate credentials
     */
    protected function help(): void
    {
        $this->oOutput->writeln('');
        $this->oOutput->writeln('To generate new access credentials:');
        $this->oOutput->writeln('');
        $this->oOutput->writeln('<comment>1:</comment> Generate a new identity here: <info>https://console.aws.amazon.com/iam/home</info>');
        $this->oOutput->writeln('  <comment>a:</comment> Enable programmatic access');
        $this->oOutput->writeln('  <comment>b:</comment> Add to the <info>SHED-CLI-TOOL</info> user group');
        $this->oOutput->writeln('<comment>2:</comment> Run: <info>shed auth:amazon</info>');
        $this->oOutput->writeln('<comment>3:</comment> Specify the access key');
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
        Api\Amazon::test($this->sLabel, $sToken);
    }
}
