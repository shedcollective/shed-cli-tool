<?php

namespace Shed\Cli\Server\Provider;

use DigitalOceanV2\Entity\Droplet;
use DigitalOceanV2\Entity\Network;
use DigitalOceanV2\ResultPager;
use phpseclib3\Crypt\EC;
use Shed\Cli\Command\Auth;
use Shed\Cli\Command\Server\Create;
use Shed\Cli\Entity;
use Shed\Cli\Entity\Provider\Account;
use Shed\Cli\Entity\Provider\Image;
use Shed\Cli\Entity\Provider\Region;
use Shed\Cli\Entity\Provider\Size;
use Shed\Cli\Exceptions\CliException;
use Shed\Cli\Exceptions\Server\TimeoutException;
use Shed\Cli\Interfaces;
use Shed\Cli\Server;
use Shed\Cli\Server\Provider\Api;

final class DigitalOcean extends Server\Provider implements Interfaces\Provider
{
    /**
     * The available Digital Ocean droplet sizes
     *
     * @var array
     */
    const SIZES = [
        [
            'slug'  => 's-1vcpu-1gb',
            'label' => 'Micro ($5/m; 1Gb, 1 VCPU)',
        ],
        [
            'slug'  => 's-1vcpu-2gb',
            'label' => 'Small ($10/m - 2Gb, 1 VCPU)',
        ],
        [
            'slug'  => 's-2vcpu-4gb',
            'label' => 'Medium ($20/m - 4Gb, 2 VCPU)',
        ],
        [
            'slug'  => 's-4vcpu-8gb',
            'label' => 'Large ($40/m - 8Gb, 4 VCPU)',
        ],
    ];

    // --------------------------------------------------------------------------

    /**
     * The Digital Ocean API
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

    /**
     * The returned images
     *
     * @var array
     */
    private $aImages;

    // --------------------------------------------------------------------------

    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Digital Ocean';
    }

    // --------------------------------------------------------------------------

    /**
     * Return an array of accounts
     *
     * @return array
     * @throws CliException
     */
    public function getAccounts(): array
    {
        $aOut = Auth\DigitalOcean::getAccounts();

        if (empty($aOut)) {
            throw new CliException(
                'No ' . Auth\DigitalOcean::LABEL . ' accounts registered; use `shed auth:' . Auth\DigitalOcean::SLUG . '` to add an account'
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
     * @throws \DigitalOceanV2\Exception\ExceptionInterface
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
        foreach (self::SIZES as $aSize) {
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
     * @throws \DigitalOceanV2\Exception\ExceptionInterface
     */
    public function getImages(Account $oAccount): array
    {
        $this->fetchImages($oAccount);
        $aOut = [];
        foreach ($this->aImages as $oImage) {
            $aOut[$oImage->id] = new Image($oImage->name, $oImage->id);
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
     * @param string         $sDomain      The configured domain name
     * @param string         $sHostname    The configured hostname name
     * @param string         $sEnvironment The configured environment
     * @param string         $sFramework   The configured framework
     * @param Account        $oAccount     The configured account
     * @param Region         $oRegion      The configured region
     * @param Size           $oSize        The configured size
     * @param Image          $oImage       The configured image
     * @param array          $aOptions     The configured options
     * @param array          $aKeywords    The configured keywords
     * @param string         $sDeployKey   The deploy key, if any, to assign to the deploy user
     * @param EC\PrivateKey $oRootKey     Temporary root ssh key
     *
     * @return Entity\Server
     * @throws \DigitalOceanV2\Exception\ExceptionInterface
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

        //  Add key to DO temporarily
        $oKey = $this
            ->getApi($oAccount)
            ->getApi()
            ->key()
            ->create(
                'temp-key-' . uniqid(),
                $oRootKey->getPublicKey()->toString('OpenSSH')
            );

        $aData = [
            'name'              => $sHostname,
            'region'            => $oRegion->getSlug(),
            'size'              => $oSize->getSlug(),
            'image'             => $oImage->getSlug(),
            'backups'           => $sEnvironment === Create::ENV_PRODUCTION,
            'ipv6'              => false,
            'privateNetworking' => false,
            'sshKeys'           => [$oKey->id],
            'userData'          => '',
            'monitoring'        => true,
            'volumes'           => [],
            'tags'              => $aKeywords,
        ];

        $oDroplet = $this
            ->getApi($oAccount)
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
                $aData['tags']
            );

        $oDroplet = $this->waitForDropletToBeActive($oAccount, $oDroplet, 300);

        $oServer = new Entity\Server();
        $oDisk   = new Entity\Provider\Disk($oDroplet->disk, $oDroplet->disk);

        //  Remove the temporary SSH key
        $this->getApi($oAccount)->getApi()->key()->remove($oKey->id);

        //  Get the public IP
        $aIps = array_filter($oDroplet->networks, function (Network $oNetwork) {
            return $oNetwork->type === 'public';
        });
        $oIp  = reset($aIps);

        return $oServer
            ->setLabel($oDroplet->name)
            ->setSlug($oDroplet->name)
            ->setId($oDroplet->id)
            ->setIp($oIp->ipAddress)
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
     * Fetch and cache regions from Digital Ocean
     *
     * @param Account $oAccount The account to use
     *
     * @throws \DigitalOceanV2\Exception\ExceptionInterface
     */
    private function fetchRegions(Account $oAccount)
    {
        if (empty($this->aRegions)) {

            $oApi   = $this->getApi($oAccount);
            $oPager = new ResultPager($oApi->getApi());

            $this->aRegions = array_values(
                array_filter(
                    $oPager->fetchAll($oApi->getRegionApi(), 'getAll'),
                    function ($oRegion) {
                        return $oRegion->available;
                    }
                )
            );
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Fetch and cache images from Digital Ocean
     *
     * @param Account $oAccount The account to use
     *
     * @throws \DigitalOceanV2\Exception\ExceptionInterface
     */
    private function fetchImages(Account $oAccount)
    {
        if (empty($this->aImages)) {

            $oApi   = $this->getApi($oAccount);
            $oPager = new ResultPager($oApi->getApi());

            $this->aImages = array_values(
                array_filter(
                    $oPager->fetchAll($oApi->getImageApi(), 'getAll', [
                        [
                            'type'    => 'snapshot',
                            'private' => true,
                        ],
                    ]),
                    function ($oImage) {
                        return $oImage->type === 'snapshot';
                    }
                )
            );
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the DO API
     *
     * @param Account $oAccount The account to use
     *
     * @return Api\DigitalOcean
     */
    private function getApi(Account $oAccount): Api\DigitalOcean
    {
        if (empty($this->oDigitalOcean)) {
            $this->oDigitalOcean = new Api\DigitalOcean($oAccount);
        }

        return $this->oDigitalOcean;
    }

    // --------------------------------------------------------------------------

    /**
     * Waits for the droplet to become active
     *
     * @param Account                        $oAccount
     * @param \DigitalOceanV2\Entity\Droplet $oDroplet
     * @param int|null                       $iTimeout
     *
     * @return Droplet
     * @throws \DigitalOceanV2\Exception\ExceptionInterface
     */
    private function waitForDropletToBeActive(Account $oAccount, Droplet $oDroplet, int $iTimeout = null): Droplet
    {
        $iEndTime = time() + ($iTimeout ?? 300);

        while (time() < $iEndTime) {

            sleep(min(20, $iEndTime - time()));

            $oDroplet = $this
                ->getApi($oAccount)
                ->getDropletApi()
                ->getById($oDroplet->id);

            if ($oDroplet->status === 'active') {
                return $oDroplet;
            }
        }

        throw new TimeoutException(sprintf(
            'Timed-out whilst waiting for droplet %s to become active.',
            $oDroplet->id
        ));
    }
}
