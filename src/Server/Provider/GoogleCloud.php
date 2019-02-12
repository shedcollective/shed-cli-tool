<?php

namespace Shed\Cli\Server\Provider;

use Shed\Cli\Interfaces\Provider;
use Shed\Cli\Resources\Provider\Account;
use Shed\Cli\Resources\Provider\Image;
use Shed\Cli\Resources\Provider\Region;
use Shed\Cli\Resources\Provider\Size;
use Shed\Cli\Resources\Server;

final class GoogleCloud extends Base implements Provider
{
    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Google Cloud';
    }

    // --------------------------------------------------------------------------

    /**
     * Return an array of accounts
     *
     * @return array
     */
    public function getAccounts(): array
    {
        //  @todo (Pablo - 2019-02-12) - Complete this method
        return [];
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
     * Create the server
     *
     * @param string  $sDomain      The configured domain name
     * @param string  $sEnvironment The configured environment
     * @param string  $sFramework   The configured framework
     * @param Account $oAccount     The configured account
     * @param Region  $oRegion      The configured region
     * @param Size    $oSize        The configured size
     * @param Image   $oImage       The configured image
     * @param array   $aOptions     The configured options
     * @param array   $aKeywords    The configured keywords
     *
     * @return Server
     */
    public function create(
        string $sDomain,
        string $sEnvironment,
        string $sFramework,
        Account $oAccount,
        Region $oRegion,
        Size $oSize,
        Image $oImage,
        array $aOptions,
        array $aKeywords
    ): Server {
        $this->oOutput->writeln('');
        $this->oOutput->writeln('🚧 Deploying Google Cloud servers command is a work in progress');
        $this->oOutput->writeln('');
    }

    // --------------------------------------------------------------------------

    /**
     * Destroy the server
     */
    public function destroy(): void
    {
    }
}
