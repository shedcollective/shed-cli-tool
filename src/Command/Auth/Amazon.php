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

    // --------------------------------------------------------------------------

    /**
     * Show help on how to generate credentials
     */
    protected function help(): void
    {
        //  @todo (Pablo - 2019-02-18) - Write help
    }

    // --------------------------------------------------------------------------

    /**
     * Verify a token is valid
     *
     * @param string $sToken The token to validate
     */
    protected function testToken(string $sToken): void
    {
        Api\Amazon::test($sToken);
    }
}
