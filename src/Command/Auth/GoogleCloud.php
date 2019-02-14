<?php

namespace Shed\Cli\Command\Auth;

use Shed\Cli\Command\Auth;
use Shed\Cli\Server\Provider\Api;

final class GoogleCloud extends Auth
{
    /**
     * The third party slug
     *
     * @var string
     */
    const SLUG = 'googlecloud';

    /**
     * The third party name
     *
     * @var string
     */
    const LABEL = 'Google Cloud';

    // --------------------------------------------------------------------------

    /**
     * Verify a token is valid
     *
     * @param string $sToken The token to validate
     */
    protected function testToken(string $sToken): void
    {
        //  @todo (Pablo - 2019-02-14) - Validate token
    }
}
