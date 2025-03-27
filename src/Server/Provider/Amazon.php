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
use Shed\Cli\Helper\Debug;
use Shed\Cli\Interfaces;
use Shed\Cli\Server;

final class Amazon extends Server\Provider implements Interfaces\Provider
{
    /**
     * Human friendly names of AWS regions
     *
     * @var array
     */
    const REGION_HUMAN = [
        'us-east-2'      => 'US East (Ohio)',
        'us-east-1'      => 'US East (N. Virginia)',
        'us-west-1'      => 'US West (N. California)',
        'us-west-2'      => 'US West (Oregon)',
        'ap-east-1'      => 'Asia Pacific (Hong Kong)',
        'ap-south-1'     => 'Asia Pacific (Mumbai)',
        'ap-northeast-3' => 'Asia Pacific (Osaka-Local)',
        'ap-northeast-2' => 'Asia Pacific (Seoul)',
        'ap-southeast-1' => 'Asia Pacific (Singapore)',
        'ap-southeast-2' => 'Asia Pacific (Sydney)',
        'ap-northeast-1' => 'Asia Pacific (Tokyo)',
        'ca-central-1'   => 'Canada (Central)',
        'cn-north-1'     => 'China (Beijing)',
        'cn-northwest-1' => 'China (Ningxia)',
        'eu-central-1'   => 'EU (Frankfurt)',
        'eu-west-1'      => 'EU (Ireland)',
        'eu-west-2'      => 'EU (London)',
        'eu-west-3'      => 'EU (Paris)',
        'eu-north-1'     => 'EU (Stockholm)',
        'me-south-1'     => 'Middle East (Bahrain)',
        'sa-east-1'      => 'South America (Sao Paulo)',
        'us-gov-east-1'  => 'AWS GovCloud (US-East)',
        'us-gov-west-1'  => 'AWS GovCloud (US-West)',
    ];

    /**
     * The available AWS images
     *
     * @var array
     */
    const IMAGES = [
        [
            'slug'  => 'aws-linux-docker',
            'label' => 'Docker',
        ],
    ];

    /**
     * The available EC2 instance sizes
     *
     * @var array
     */
    const SIZES = [
        [
            'slug'  => 't3.nano',
            'label' => 'Micro (0.5Gb, 1 vCPU)',
        ],
        [
            'slug'  => 'a1.medium',
            'label' => 'Small (2Gb, 1 vCPU)',
        ],
        [
            'slug'  => 'a1.large',
            'label' => 'Medium (4Gb, 2 vCPU)',
        ],
        [
            'slug'  => 'a1.xlarge',
            'label' => 'Large (8Gb, 4 vCPU)',
        ],
    ];

    /**
     * The base image to use for all instances (Ubuntu Server 18.04 LTS (HVM), SSD Volume Type)
     *
     * @var string
     */
    const BASE_IMAGE = 'ami-0be057a22c63962cb';

    // --------------------------------------------------------------------------

    /**
     * The AWS API
     *
     * @var Api\Amazon
     */
    private $oAmazon;

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
        return 'AWS';
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
        $this->fetchRegions($oAccount);
        $aOut = [];
        foreach ($this->aRegions as $aRegion) {

            $sRegion = array_key_exists($aRegion['RegionName'], static::REGION_HUMAN)
                ? static::REGION_HUMAN[$aRegion['RegionName']]
                : $aRegion['RegionName'];

            $aOut[$aRegion['RegionName']] = new Region($sRegion, $aRegion['RegionName']);
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
     * Returns the how long to wait for SSH
     *
     * @return int
     */
    public function getSshInitialWait(): int
    {
        return 30;
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

        //  @todo (Pablo - 2020-01-09) - Launch an EC2 instance
        $oInstance = $this
            ->oAmazon
            ->getApi()
            ->runInstances([
                'ImageId'      => static::BASE_IMAGE,
                'InstanceType' => $oSize->getSlug(),
                'MinCount'     => 1,
                'MaxCount'     => 1,
                /** Security Groups? */
            ]);

        //  @todo (Pablo - 2020-01-09) - Populate the Server entity to return
        throw new CliException('🚧 Deploying AWS servers is a work in progress');

        return (new Entity\Server())
            ->setLabel(/** populate this */)
            ->setSlug(/** populate this */)
            ->setId(/** populate this */)
            ->setIp(/** populate this */)
            ->setDomain($sDomain)
            ->setDisk(
                new Entity\Provider\Disk(
                /** populate this */
                )
            )
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
     * Fetch and cache regions from Digital Ocean
     *
     * @param Account $oAccount The account to use
     */
    private function fetchRegions(Account $oAccount)
    {
        if (empty($this->aRegions)) {
            $this->oAmazon  = new Api\Amazon($oAccount);
            $this->aRegions = $this->oAmazon->getApi()->describeRegions()->get('Regions');
        }
    }
}
