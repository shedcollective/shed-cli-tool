<?php

namespace Shed\Cli\Server\Provider\Api;

use DigitalOceanV2\Adapter\BuzzAdapter;
use DigitalOceanV2\Api;
use DigitalOceanV2\Entity;
use DigitalOceanV2\DigitalOceanV2;

final class DigitalOcean
{
    /**
     * The access token to use
     *
     * @var string
     */
    private $sAccessToken = '';

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
     * @param string $sAccessToken The access token to use
     */
    public function __construct($sAccessToken = '')
    {
        $this->sAccessToken = $sAccessToken;
        $oAdapter           = new BuzzAdapter($sAccessToken);
        $this->oApi         = new DigitalOceanV2($oAdapter);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the access token being used
     *
     * @return string
     */
    public function getAccessToken(): string
    {
        return $this->sAccessToken;
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
     * Return the DO Account API
     *
     * @return \DigitalOceanV2\Api\Account
     */
    public function getAccountApi(): Api\Account
    {
        return $this->getApi()->account();
    }

    // --------------------------------------------------------------------------

    /**
     * Return the DO Region API
     *
     * @return \DigitalOceanV2\Api\Region
     */
    public function getRegionApi(): Api\Region
    {
        return $this->getApi()->region();
    }

    // --------------------------------------------------------------------------

    /**
     * Return the DO Image API
     *
     * @return \DigitalOceanV2\Api\Image
     */
    public function getImageApi(): Api\Image
    {
        return $this->getApi()->image();
    }

    // --------------------------------------------------------------------------

    /**
     * Return the DO Droplet API
     *
     * @return \DigitalOceanV2\Api\Droplet
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
