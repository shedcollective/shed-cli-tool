<?php

namespace Shed\Cli\Server\Provider\Api;

use DigitalOceanV2\Api;
use DigitalOceanV2\Client;
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
     * @var Client
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
        $this->oApi     = new Client();

        $this->oApi->authenticate($this->oAccount->getToken());
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
     * @return Client
     */
    public function getApi(): Client
    {
        return $this->oApi;
    }

    // --------------------------------------------------------------------------

    /**
     * Test the connection
     *
     * @param string $sToken The token to test
     *
     * @throws \DigitalOceanV2\Exception\ExceptionInterface
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
     * @return Entity\Account
     * @throws \DigitalOceanV2\Exception\ExceptionInterface
     */
    public function getUserInformation(): Entity\Account
    {
        return $this->getAccountApi()->getUserInformation();
    }
}
