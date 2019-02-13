<?php

namespace Shed\Cli\Server\Provider;

use Shed\Cli\Command\Server\Create;
use Shed\Cli\Interfaces;
use Shed\Cli\Entity;
use Shed\Cli\Entity\Provider\Account;
use Shed\Cli\Entity\Provider\Image;
use Shed\Cli\Entity\Provider\Region;
use Shed\Cli\Entity\Provider\Size;
use Shed\Cli\Server;
use Shed\Cli\Server\Provider\Api;

final class DigitalOcean extends Server\Provider implements Interfaces\Provider
{
    /**
     * The available DigitalOcean images
     *
     * @var array
     */
    const IMAGES = [
        [
            'slug'  => 'docker-18-04',
            'label' => 'Docker',
        ],
        [
            'slug'  => 'lamp-18-04',
            'label' => 'LAMP',
        ],
        [
            'slug'  => 'wordpress-18-04',
            'label' => 'WordPress',
        ],
        [
            'slug'  => 'mysql-18-04',
            'label' => 'MySQL',
        ],
    ];

    /**
     * The available DigitalOcean droplet sizes
     *
     * @var array
     */
    const SIZES = [
        [
            'slug'  => '1gb',
            'label' => 'Micro ($5/m; 1Gb)',
        ],
        [
            'slug'  => '2gb',
            'label' => 'Small ($10/m - 2Gb)',
        ],
        [
            'slug'  => '4gb',
            'label' => 'Medium ($20/m - 4Gb)',
        ],
        [
            'slug'  => '8gb',
            'label' => 'Large ($40/m - 8Gb)',
        ],
    ];

    // --------------------------------------------------------------------------

    /**
     * The digital ocean API
     *
     * @var Api\DigitalOcean
     */
    private $oDigitalOcean;

    /**
     * The returned regions
     *
     * @var array
     */
    private $aRegions;

    // --------------------------------------------------------------------------

    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'DigitalOcean';
    }

    // --------------------------------------------------------------------------

    /**
     * Return an array of accounts
     *
     * @return array
     */
    public function getAccounts(): array
    {
        $aOut = [];
        foreach (Api\DigitalOcean::getAccounts() as $sLabel => $sSecret) {
            $aOut[$sLabel] = new Account($sLabel, $sSecret);
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
        $this->fetchRegions($oAccount);
        $aOut = [];
        foreach ($this->aRegions as $oRegion) {
            $aOut[$oRegion->slug] = new Region($oRegion->name, $oRegion->slug);
        }

        return $aOut;
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
        $aOut = [];
        foreach (static::SIZES as $aSize) {
            $aOut[$aSize['slug']] = new Size($aSize['label'], $aSize['slug']);
        }
        return $aOut;
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
        $aOut = [];
        foreach (static::IMAGES as $aImage) {
            $aOut[$aImage['slug']] = new Image($aImage['label'], $aImage['slug']);
        }
        return $aOut;
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
     * @return Entity\Server
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
    ): Entity\Server {

        $aData = [
            'name'              => implode(
                '-',
                array_map(
                    function ($sBit) {
                        return preg_replace('/[^a-z0-9\-]/', '', str_replace('.', '-', strtolower($sBit)));
                    },
                    [
                        $sDomain,
                        $oImage->getLabel(),
                        $sEnvironment,
                        $sFramework,
                    ]
                )
            ),
            'region'            => $oRegion->getSlug(),
            'size'              => $oSize->getSlug(),
            'image'             => $oImage->getSlug(),
            'backups'           => $sEnvironment === Create::ENV_PRODUCTION,
            'ipv6'              => false,
            'privateNetworking' => false,
            'sshKeys'           => [],
            'userData'          => '',
            'monitoring'        => true,
            'volumes'           => [],
            'tags'              => $aKeywords,
            'wait'              => true,
        ];

        //  @todo (Pablo - 2019-02-07) - Fetch local key + global keys from account

        $oDroplet = $this
            ->oDigitalOcean
            ->getDropletApi()
            ->create(
                $aData['name'],
                $aData['region'],
                $aData['size'],
                $aData['image'],
                $aData['backups'],
                $aData['ipv6'],
                $aData['privateNetworking'],
                $aData['sshKeys'],
                $aData['userData'],
                $aData['monitoring'],
                $aData['volumes'],
                $aData['tags'],
                $aData['wait']
            );

        $oServer = new Entity\Server();
        $oDisk   = new Entity\Provider\Disk($oDroplet->disk, $oDroplet->disk);
        return $oServer
            ->setLabel($oDroplet->name)
            ->setSlug($oDroplet->name)
            ->setId($oDroplet->id)
            ->setIp($oDroplet->networks[0]->ipAddress)
            ->setDomain($sDomain)
            ->setDisk($oDisk)
            ->setImage($oImage)
            ->setRegion($oRegion)
            ->setSize($oSize);
    }

    // --------------------------------------------------------------------------

    /**
     * Destroy the server
     */
    public function destroy(): void
    {
    }

    // --------------------------------------------------------------------------

    /**
     * Fetch and cache regions from DigitalOcean
     *
     * @param Account $oAccount The account to use
     */
    private function fetchRegions(Account $oAccount)
    {
        if (empty($this->aRegions)) {
            $this->oDigitalOcean = new Api\DigitalOcean($oAccount->getSecret());
            $this->aRegions      = $this->oDigitalOcean->getRegionApi()->getAll();
            $this->aRegions      = array_values(
                array_filter(
                    $this->aRegions,
                    function ($oRegion) {
                        return $oRegion->available;
                    }
                )
            );
        }
    }
}
