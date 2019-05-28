<?php

namespace Shed\Cli\Server\Provider\Api;

use DigitalOceanV2\Adapter\BuzzAdapter;
use DigitalOceanV2\Api;
use DigitalOceanV2\DigitalOceanV2;
use DigitalOceanV2\Entity;
use Shed\Cli\Entity\Provider\Account;

final class DigitalOcean
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
     * @var DigitalOceanV2
     */
    private $oApi;

    // --------------------------------------------------------------------------

    /**
     * Auth constructor.
     *
     * @param Account $oAccount The account to use
     */
    public function __construct(Account $oAccount)
    {
        $this->oAccount = $oAccount;
        $oAdapter       = new BuzzAdapter($this->oAccount->getToken());
        $this->oApi     = new DigitalOceanV2($oAdapter);
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
     *
     * @return DigitalOceanV2
     */
    public function getApi(): DigitalOceanV2
    {
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
        $oApi = new self(new Account('', $sToken));
        $oApi->getUserInformation();
    }

    // --------------------------------------------------------------------------

    /**
     * Return the DO Account API
     *
     * @return Api\Account
     */
    public function getAccountApi(): Api\Account
    {
        return $this->getApi()->account();
    }

    // --------------------------------------------------------------------------

    /**
     * Return the DO Region API
     *
     * @return Api\Region
     */
    public function getRegionApi(): Api\Region
    {
        return $this->getApi()->region();
    }

    // --------------------------------------------------------------------------

    /**
     * Return the DO Image API
     *
     * @return Api\Image
     */
    public function getImageApi(): Api\Image
    {
        return $this->getApi()->image();
    }

    // --------------------------------------------------------------------------

    /**
     * Return the DO Droplet API
     *
     * @return Api\Droplet
     */
    public function getDropletApi(): Api\Droplet
    {
        return $this->getApi()->droplet();
    }

    // --------------------------------------------------------------------------

    /**
     * Return information of the authenticated user
     *
     * @return \DigitalOceanV2\Entity\Account
     */
    public function getUserInformation(): Entity\Account
    {
        return $this->getAccountApi()->getUserInformation();
    }
}
