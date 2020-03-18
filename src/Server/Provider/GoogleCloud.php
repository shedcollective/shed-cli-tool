<?php

namespace Shed\Cli\Server\Provider;

use Exception;
use Google_Service_Compute_AccessConfig;
use Google_Service_Compute_AttachedDisk;
use Google_Service_Compute_Disk;
use Google_Service_Compute_Instance;
use Google_Service_Compute_Metadata;
use Google_Service_Compute_MetadataItems;
use Google_Service_Compute_NetworkInterface;
use Google_Service_Compute_Tags;
use Shed\Cli\Command\Auth;
use Shed\Cli\Entity;
use Shed\Cli\Entity\Provider\Account;
use Shed\Cli\Entity\Provider\Image;
use Shed\Cli\Entity\Provider\Region;
use Shed\Cli\Entity\Provider\Size;
use Shed\Cli\Exceptions\CliException;
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
            'slug'  => 'google-linux-docker',
            'label' => 'Docker',
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
            'label' => 'Medium (3.75Gb)',
        ],
        [
            'slug'  => 'n1-standard-2',
            'label' => 'Large (7.5Gb)',
        ],
    ];

    /**
     * The available Google Cloud regions
     *
     * @var array
     */
    const REGIONS = [
        [
            'slug'  => 'europe-west2-a',
            'label' => 'London, England, UK',
        ],
        [
            'slug'  => 'us-west2-a',
            'label' => 'Los Angeles, California, USA',
        ],
        [
            'slug'  => 'asia-east1-a',
            'label' => 'Changhua County, Taiwan',
        ],
        [
            'slug'  => 'asia-east2-a',
            'label' => 'Hong Kong',
        ],
        [
            'slug'  => 'asia-north-a',
            'label' => 'Tokyo, Japan',
        ],
        [
            'slug'  => 'asia-south-a',
            'label' => 'Mumbai, India',
        ],
        [
            'slug'  => 'asia-south-a',
            'label' => 'Jurong West, Singapore',
        ],
        [
            'slug'  => 'australia-south-a',
            'label' => 'Sydney, Australia',
        ],
        [
            'slug'  => 'europe-north-a',
            'label' => 'Hamina, Finland',
        ],
        [
            'slug'  => 'europe-west1-b',
            'label' => 'St. Ghislain, Belgium',
        ],
        [
            'slug'  => 'europe-west3-a',
            'label' => 'Frankfurt, Germany',
        ],
        [
            'slug'  => 'europe-west4-a',
            'label' => 'Eemshaven, Netherlands',
        ],
        [
            'slug'  => 'northamerica-north-a',
            'label' => 'Montréal, Québec, Canada',
        ],
        [
            'slug'  => 'southamerica-east1-a',
            'label' => 'São Paulo, Brazil',
        ],
        [
            'slug'  => 'us-centr-a',
            'label' => 'Council Bluffs, Iowa, USA',
        ],
        [
            'slug'  => 'us-east1-b',
            'label' => 'Moncks Corner, South Carolina, USA',
        ],
        [
            'slug'  => 'us-east4-a',
            'label' => 'Ashburn, Northern Virginia, USA',
        ],
        [
            'slug'  => 'us-west1-a',
            'label' => 'The Dalles, Oregon, USA',
        ],
    ];

    /**
     * The base image to use for all droplets
     *
     * @var string
     */
    const BASE_IMAGE = 'projects/ubuntu-os-cloud/global/images/ubuntu-1910-eoan-v20200317';

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
        $aOut = [];
        foreach (static::REGIONS as $aSize) {
            $aOut[$aSize['slug']] = new Region($aSize['label'], $aSize['slug']);
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
     * @param string  $sDeployKey   The deploy key, if any, to assign to the deployhq user
     *
     * @return Entity\Server
     * @throws Exception
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
        array $aKeywords,
        string $sDeployKey
    ): Entity\Server {

        $oApi = new Api\GoogleCloud($oAccount);

        //  Prep variables
        $sProjectId = $oApi->getKeyObject()->project_id;
        $sZone      = $oRegion->getSlug();
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
        $sDiskName  = $sName . '-disk';

        try {

            //  Disks
            //  https://github.com/PaulRashidi/compute-getting-started-php/blob/master/app.php#L209
            //  Create a new boot disks
            $oDisk = new Google_Service_Compute_Disk();
            $oDisk->setName($sDiskName);
            $oDisk->setSourceImage(static::BASE_IMAGE);
            $oDisk->setSizeGb(10);

            //  Insert disk
            $oInsertDiskOperation = $oApi
                ->getApi()
                ->disks
                ->insert(
                    $sProjectId,
                    $sZone,
                    $oDisk
                );

            //  Wait
            if (!$oApi::wait($oApi->getApi(), $sProjectId, $sZone, $oInsertDiskOperation->getName())) {
                throw new CliException('Failed to create disk');
            }

            //  Fetch the disk
            $oBootDisk = $oApi->getApi()->disks->get($sProjectId, $sZone, $sDiskName);
            if ($oBootDisk->getStatus() !== 'READY') {
                throw new CliException('Failed to fetch boot disk');
            }

            $oPrimaryDisk = new Google_Service_Compute_AttachedDisk();
            $oPrimaryDisk->setBoot('TRUE');
            $oPrimaryDisk->setDeviceName('primary');
            $oPrimaryDisk->setMode('READ_WRITE');
            $oPrimaryDisk->setSource($oBootDisk->getSelfLink());
            $oPrimaryDisk->setType('PERSISTENT');

            // --------------------------------------------------------------------------

            //  Define meta data
            $oBlockKeys = new Google_Service_Compute_MetadataItems();
            $oBlockKeys->setKey('block-project-ssh-keys');
            $oBlockKeys->setValue(true);

            $oStartupScript = new Google_Service_Compute_MetadataItems();
            $oStartupScript->setKey('startup-script');
            $oStartupScript->setValue(static::getStartupScript($oImage, $sDeployKey));

            $oMetadata = new Google_Service_Compute_Metadata();
            $oMetadata->setItems([$oBlockKeys, $oStartupScript]);

            // --------------------------------------------------------------------------

            //  Define the Networks
            $oAccessConfig = new Google_Service_Compute_AccessConfig();
            $oAccessConfig->setName('External NAT');
            $oAccessConfig->setType('ONE_TO_ONE_NAT');

            $oNetwork = new Google_Service_Compute_NetworkInterface();
            $oNetwork->setNetwork('/global/networks/default');
            $oNetwork->setAccessConfigs([$oAccessConfig]);

            // --------------------------------------------------------------------------

            //  Define the tags
            $aKeywords[] = 'https-server';
            $aKeywords[] = 'http-server';

            $oTags = new Google_Service_Compute_Tags();
            $oTags->setItems($aKeywords);

            // --------------------------------------------------------------------------

            //  Make the request
            $oRequestBody = new Google_Service_Compute_Instance();

            //  Request Body
            $oRequestBody->setName($sName);
            $oRequestBody->setMachineType('zones/' . $sZone . '/machineTypes/' . $oSize->getSlug());
            $oRequestBody->setTags($oTags);
            $oRequestBody->setNetworkInterfaces([$oNetwork]);
            $oRequestBody->setDisks([$oPrimaryDisk]);
            $oRequestBody->setMetadata($oMetadata);

            $oInsertInstanceOperation = $oApi
                ->getApi()
                ->instances
                ->insert(
                    $sProjectId,
                    $sZone,
                    $oRequestBody
                );

            //  Wait
            if (!$oApi::wait($oApi->getApi(), $sProjectId, $sZone, $oInsertInstanceOperation->getName())) {
                throw new CliException('Failed to create instance');
            }

            //  Get instance
            $oInstance = $oApi
                ->getApi()
                ->instances
                ->get(
                    $sProjectId,
                    $sZone,
                    $sName
                );

            $oServer = new Entity\Server();
            $oDisk   = new Entity\Provider\Disk($sDiskName, $sDiskName);

            $aNetworkInterfaces = $oInstance->getNetworkInterfaces();
            $oNetworkInterface  = reset($aNetworkInterfaces);
            $aAccessConfigs     = $oNetworkInterface->getAccessConfigs();
            $oAccessConfig      = reset($aAccessConfigs);

            return $oServer
                ->setLabel($oInstance->name)
                ->setSlug($oInstance->name)
                ->setId($oInstance->id)
                ->setIp($oAccessConfig->getNatIP())
                ->setDomain($sDomain)
                ->setDisk($oDisk)
                ->setImage($oImage)
                ->setRegion($oRegion)
                ->setSize($oSize);

        } catch (Exception $e) {

            if (!empty($oBootDisk)) {
                $oApi
                    ->getApi()
                    ->disks
                    ->delete($sProjectId, $sZone, $sDiskName);
            }

            throw $e;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Destroy the server
     */
    public function destroy(): void
    {
    }
}
