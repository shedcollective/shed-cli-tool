<?php

namespace Shed\Cli\Server\Provider;

use phpseclib3\Crypt\EC;
use Shed\Cli\Command\Auth;
use Shed\Cli\Entity;
use Shed\Cli\Entity\Provider\Account;
use Shed\Cli\Entity\Provider\Image;
use Shed\Cli\Entity\Provider\Region;
use Shed\Cli\Entity\Provider\Size;
use Shed\Cli\Exceptions\CliException;
use Shed\Cli\Interfaces;
use Shed\Cli\Server;

final class Amazon extends Server\Provider implements Interfaces\Provider
{
    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Amazon Web Services';
    }

    // --------------------------------------------------------------------------

    /**
     * Return an array of accounts
     *
     * @return array
     */
    public function getAccounts(): array
    {
        $aOut = Auth\Amazon::getAccounts();

        if (empty($aOut)) {
            throw new CliException(
                'No ' . Auth\Amazon::LABEL . ' accounts registered; use `shed auth:' . Auth\Amazon::SLUG . '` to add an account'
            );
        }

        return $aOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Return an array of supported regions
     *
     * @param Account $oAccount The selected provider account
     *
     * @return array
     */
    public function getRegions(Account $oAccount): array
    {
        //  @todo (Pablo - 2019-02-12) - Complete this method
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Return an array of supported sizes
     *
     * @param Account $oAccount The selected provider account
     *
     * @return array
     */
    public function getSizes(Account $oAccount): array
    {
        //  @todo (Pablo - 2019-02-12) - Complete this method
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Return an array of supported images
     *
     * @param Account $oAccount The selected provider account
     *
     * @return array
     */
    public function getImages(Account $oAccount): array
    {
        //  @todo (Pablo - 2019-02-12) - Complete this method
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * The configurable options for the framework
     *
     * @return array
     */
    public function getOptions(): array
    {
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Returns any ENV vars for the project
     *
     * @return array
     */
    public function getEnvVars(): array
    {
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the how long to wait for SSH
     *
     * @return int
     */
    public function getSshInitialWait(): int
    {
        return 20;
    }

    // --------------------------------------------------------------------------

    /**
     * Create the server
     *
     * @param string        $sDomain      The configured domain name
     * @param string        $sHostname    The configured hostname name
     * @param string        $sEnvironment The configured environment
     * @param string        $sFramework   The configured framework
     * @param Account       $oAccount     The configured account
     * @param Region        $oRegion      The configured region
     * @param Size          $oSize        The configured size
     * @param Image         $oImage       The configured image
     * @param array         $aOptions     The configured options
     * @param array         $aKeywords    The configured keywords
     * @param string        $sDeployKey   The deploy key, if any, to assign to the deploy user
     * @param EC\PrivateKey $oRootKey     Temporary root ssh key
     *
     * @return Entity\Server
     */
    public function create(
        string $sDomain,
        string $sHostname,
        string $sEnvironment,
        string $sFramework,
        Account $oAccount,
        Region $oRegion,
        Size $oSize,
        Image $oImage,
        array $aOptions,
        array $aKeywords,
        string $sDeployKey,
        EC\PrivateKey $oRootKey
    ): Entity\Server {
        throw new CliException('🚧 Deploying AWS servers is a work in progress');
    }

    // --------------------------------------------------------------------------

    /**
     * Destroy the server
     */
    public function destroy(): void
    {
    }
}
