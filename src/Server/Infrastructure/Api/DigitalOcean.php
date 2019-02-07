<?php

namespace Shed\Cli\Server\Infrastructure\Api;

use DigitalOceanV2\Adapter\BuzzAdapter;
use DigitalOceanV2\Api;
use DigitalOceanV2\Entity;
use DigitalOceanV2\DigitalOceanV2;
use Shed\Cli\Exceptions\DigitalOcean\AccountNotFoundException;
use Shed\Cli\Helper\Config;

final class DigitalOcean
{
    /**
     * Config key containing accounts
     *
     * @var string
     */
    const CONFIG_ACCOUNTS_KEY = 'server.infrastructure.digitalocean.accounts';

    // --------------------------------------------------------------------------

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
     * DigitalOcean constructor.
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
     * Return all configured accounts
     *
     * @return array
     */
    public static function getAccounts(): array
    {
        return (array) Config::get(static::CONFIG_ACCOUNTS_KEY) ?: [];
    }

    // --------------------------------------------------------------------------

    /**
     * Get an account at a specific index
     *
     * @param int $iIndex The index to look for
     *
     * @return \stdClass
     */
    public static function getAccountByIndex(int $iIndex): \stdClass
    {
        $aAccounts = static::getAccounts();
        $aKeys     = array_keys($aAccounts);

        if (!array_key_exists($iIndex, $aKeys)) {
            throw new AccountNotFoundException('No account at index "' . $iIndex . '"');
        }
        return (object) [
            'label' => $aKeys[$iIndex],
            'token' => $aAccounts[$aKeys[$iIndex]],
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Get an account by it's label
     *
     * @param string $sLabel The label to search for
     *
     * @return \stdClass
     */
    public static function getAccountByLabel(string $sLabel): \stdClass
    {
        $aAccounts = static::getAccounts();

        if (!array_key_exists($sLabel, $aAccounts)) {
            throw new AccountNotFoundException('No account with label "' . $sLabel . '"');
        }

        return (object) [
            'label' => $sLabel,
            'token' => $aAccounts[$sLabel],
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Get an account by it's token
     *
     * @param string $sToken the token to search for
     *
     * @return \stdClass
     */
    public static function getAccountBytoken(string $sToken): \stdClass
    {
        $aAccounts = static::getAccounts();
        $sLabel    = array_search($sToken, $aAccounts);

        if ($sLabel === false) {
            throw new AccountNotFoundException('No account with token "' . $$sToken . '"');
        }

        return (object) [
            'label' => $sLabel,
            'token' => $sToken,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Add an account
     *
     * @param string $sLabel The account label
     * @param string $sToken the account token
     */
    public static function addAccount(string $sLabel, string $sToken): void
    {
        $aAccounts          = static::getAccounts();
        $aAccounts[$sLabel] = $sToken;
        ksort($aAccounts);
        Config::set(static::CONFIG_ACCOUNTS_KEY, $aAccounts);
    }

    // --------------------------------------------------------------------------

    /**
     * The account to delete
     *
     * @param string $sLabel
     */
    public static function deleteAccount(string $sLabel = ''): void
    {
        $aAccounts = static::getAccounts();
        unset($aAccounts[$sLabel]);
        Config::set(static::CONFIG_ACCOUNTS_KEY, $aAccounts);
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
