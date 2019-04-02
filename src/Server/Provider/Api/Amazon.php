<?php

namespace Shed\Cli\Server\Provider\Api;

use Shed\Cli\Entity\Provider\Account;

final class Amazon
{
    /**
     * The account to use
     *
     * @var Account
     */
    private $oAccount;

    /**
     * The Digital Ocean API
     *
     * @var \Google_Service_Compute
     */
    private $oApi;

    // --------------------------------------------------------------------------

    /**
     * Auth constructor.
     *
     * @param Account $oAccount The account to use
     */
    public function __construct($oAccount)
    {
        $this->oAccount = $oAccount;
        //  @todo (Pablo - 2019-02-18) - Set up API
    }

    // --------------------------------------------------------------------------

    /**
     * Return the account being used
     *
     * @return Account
     */
    public function getAccount(): Account
    {
        return $this->oAccount;
    }

    // --------------------------------------------------------------------------

    /**
     * Return the digital Ocean API
     */
    public function getApi()
    {
        //  @todo (Pablo - 2019-02-18) - Define type hints
        return $this->oApi;
    }

    // --------------------------------------------------------------------------


    /**
     * Test the connection
     *
     * @param string $sToken The token to test
     */
    public static function test(string $sToken)
    {
        //  @todo (Pablo - 2019-02-18) - test the token
    }
}
