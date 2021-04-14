<?php

namespace Shed\Cli\Command\Auth;

use Shed\Cli\Command\Auth;
use Shed\Cli\Server\Provider\Api;

final class Backup extends Auth
{
    /**
     * The third party slug
     *
     * @var string
     */
    const SLUG = 'backup';

    /**
     * The third party name
     *
     * @var string
     */
    const LABEL = 'Backup';

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
        $this->oOutput->writeln('Credentials are stored in 1Password under the Shed AWS login.');
        $this->oOutput->writeln('Look for it under <info>SERVER BACKUPS</info>');
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
