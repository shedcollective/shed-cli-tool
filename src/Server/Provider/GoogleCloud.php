<?php

namespace Shed\Cli\Server\Provider;

use Shed\Cli\Command\Auth;
use Shed\Cli\Entity;
use Shed\Cli\Entity\Provider\Account;
use Shed\Cli\Entity\Provider\Image;
use Shed\Cli\Entity\Provider\Region;
use Shed\Cli\Entity\Provider\Size;
use Shed\Cli\Exceptions\CliException;
use Shed\Cli\Helper\Debug;
use Shed\Cli\Interfaces;
use Shed\Cli\Server;

final class GoogleCloud extends Server\Provider implements Interfaces\Provider
{
    /**
     * The available Google Cloud images
     *
     * @var array
     */
    const IMAGES = [
        [
            'slug'  => 'shed-hosting-docker',
            'label' => 'Docker',
        ],
        [
            'slug'  => 'shed-hosting-lamp',
            'label' => 'LAMP',
        ],
        [
            'slug'  => 'shed-hosting-wordpress',
            'label' => 'WordPress',
        ],
        [
            'slug'  => 'shed-hosting-mysql',
            'label' => 'MySQL',
        ],
    ];

    /**
     * The available Google Cloud compute sizes
     *
     * @var array
     */
    const SIZES = [
        [
            'slug'  => 'g1-small',
            'label' => 'Small (1.7Gb)',
        ],
        [
            'slug'  => 'n1-standard-1',
            'label' => 'Standard (3.75Gb)',
        ],
        [
            'slug'  => 'n1-standard-2',
            'label' => 'Large (7.5Gb)',
        ],
    ];

    // --------------------------------------------------------------------------

    /**
     * The Google Cloud API
     *
     * @var Api\GoogleCloud
     */
    private $oGoogleCloud;

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
        $aOut = Auth\GoogleCloud::getAccounts();

        if (empty($aOut)) {
            throw new CliException(
                'No ' . Auth\GoogleCloud::LABEL . ' accounts registered; use `shed auth:' . Auth\GoogleCloud::SLUG . '` to add an account'
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
        $this->fetchRegions($oAccount);
        $aOut = [];
        foreach ($this->aRegions as $oRegion) {
            foreach ($oRegion->zones as $sZone) {
                $sZone        = preg_replace('/^.*\/([a-z0-9\-]+)$/', '$1', $sZone);
                $aOut[$sZone] = new Region($oRegion->name, $sZone);
            }
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

        //  Prep variables
        $sProjectId = $this->oGoogleCloud->getKeyObject()->project_id;
        $sName      = implode(
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
        );

        // --------------------------------------------------------------------------

        //  Disks
        //  https://github.com/PaulRashidi/compute-getting-started-php/blob/master/app.php#L209
        //  @todo (Pablo - 2019-02-18) - Create a disk
        $oDisk = new \Google_Service_Compute_Disk();
        $oDisk->setName($sName . '-disk');
        $oDisk->setSourceImage('/global/images/' . $oImage->getSlug());
        $oDisk->setSizeGb(50);

        //  Insert disk
        //  Wait

        //  Fetch the disk
        //  @todo (Pablo - 2019-02-18) - Fetch the boot disk

        // --------------------------------------------------------------------------

        //  Define the Networks
        $oNetworks = new \Google_Service_Compute_NetworkInterface();
        $oNetworks->setNetwork('/global/networks/default');

        // --------------------------------------------------------------------------

        //  Define the tags
        $oTags = new \Google_Service_Compute_Tags();
        $oTags->setItems($aKeywords);

        // --------------------------------------------------------------------------

        //  Make the request
        $oRequestBody = new \Google_Service_Compute_Instance();

        //  Request Body
        //  https://cloud.google.com/compute/docs/reference/rest/v1/instances/insert
        $oRequestBody->setName($sName);
        $oRequestBody->setMachineType('zones/' . $oRegion->getSlug() . '/machineTypes/' . $oSize->getSlug());
        $oRequestBody->setTags($oTags);
        $oRequestBody->setNetworkInterfaces([$oNetworks]);
        $oRequestBody->setDisks([$oDisk]);

        //  @todo (Pablo - 2019-02-07) - Fetch local key + global keys from account

        $oResponse = $this
            ->oGoogleCloud
            ->getApi()
            ->instances
            ->insert(
                $sProjectId,
                $oRegion->getSlug(),
                $oRequestBody
            );

        //  Wait
        //  Get instance
        $oInstance = $this
            ->oGoogleCloud
            ->getApi()
            ->instances
            ->get(
                $sProjectId,
                $oRegion->getSlug(),
                $sName
            );

        Debug::dd($oResponse);

        $oServer = new Entity\Server();
        $oDisk   = new Entity\Provider\Disk($oInstance->disk, $oInstance->disk);
        return $oServer
            ->setLabel($oInstance->name)
            ->setSlug($oInstance->name)
            ->setId($oInstance->id)
            ->setIp($oInstance->networks[0]->ipAddress)
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
     * Fetch and cache regions from Google Cloud
     *
     * @param Account $oAccount The account to use
     */
    private function fetchRegions(Account $oAccount)
    {
        if (empty($this->aRegions)) {
            $this->oGoogleCloud = new Api\GoogleCloud($oAccount);
            $this->aRegions     = $this->oGoogleCloud->getRegions();
        }
    }
}
