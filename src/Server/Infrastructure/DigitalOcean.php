<?php

namespace Shed\Cli\Server\Infrastructure;

use Shed\Cli\Exceptions\CliException;
use Shed\Cli\Helper\Debug;
use Shed\Cli\Interfaces\Infrastructure;
use Shed\Cli\Resources\Option;
use Shed\Cli\Resources\Server;
use Shed\Cli\Server\Infrastructure\Api;

final class DigitalOcean extends Base implements Infrastructure
{
    /**
     * The available DigitalOcean images
     *
     * @var array
     */
    const IMAGES = [
        [
            'id'    => '38835928',
            'slug'  => 'docker-18-04',
            'label' => 'Docker',
        ],
        [
            'id'    => '42326229',
            'slug'  => 'lamp-18-04',
            'label' => 'LAMP',
        ],
        [
            'id'    => '41320116',
            'slug'  => 'wordpress-18-04',
            'label' => 'WordPress',
        ],
        [
            'id'    => '40744498',
            'slug'  => 'mysql-18-04',
            'label' => 'MySQL',
        ],
    ];

    /**
     * The available droplet sizes
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

    /**
     * The available environments
     *
     * @var array
     */
    const ENVIRONMENTS = [
        'PRODUCTION',
        'STAGING',
    ];

    /**
     * The available frameworks
     *
     * @var array
     * @todo (Pablo - 2019-02-06) - detect these from the classes
     */
    const FRAMEWORKS = [
        'Nails',
        'Laravel',
        'WordPress',
        'Static',
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
    public function getName(): string
    {
        return 'DigitalOcean';
    }

    // --------------------------------------------------------------------------

    /**
     * The configurable options for the framework
     *
     * @return array
     */
    public function getOptions(): array
    {
        return [
            'ACCOUNT' => new Option(
                Option::TYPE_CHOOSE,
                'DO Account',
                null,
                function () {
                    $aAccounts = Api\DigitalOcean::getAccounts();
                    if (empty($aAccounts)) {
                        throw new CliException(
                            'No Digital Ocean accounts configured; `shed digitalocean:auth` to configure'
                        );
                    }
                    return array_keys($aAccounts);
                }
            ),
//            'REGION'  => new Option(
//                Option::TYPE_CHOOSE,
//                'Region',
//                null,
//                function (array $aOptions) {
//
//                    if (empty($this->aRegions)) {
//                        $oAccount            = Api\DigitalOcean::getAccountByIndex($aOptions['ACCOUNT']->getValue());
//                        $this->oDigitalOcean = new Api\DigitalOcean($oAccount->token);
//                        $this->aRegions      = $this->oDigitalOcean->getRegionApi()->getAll();
//                        $this->aRegions      = array_values(
//                            array_filter(
//                                $this->aRegions,
//                                function ($oRegion) {
//                                    return $oRegion->available;
//                                }
//                            )
//                        );
//                    }
//
//                    $aOptions = [];
//                    foreach ($this->aRegions as $oRegion) {
//                        $aOptions[] = $oRegion->name;
//                    }
//
//                    return $aOptions;
//                }
//            ),
//            'SIZE'    => new Option(
//                Option::TYPE_CHOOSE,
//                'Memory',
//                null,
//                function () {
//                    $aOptions = [];
//                    foreach (static::SIZES as $aSize) {
//                        $aOptions[] = $aSize['label'];
//                    }
//                    return $aOptions;
//                }
//            ),
//            'IMAGE'   => new Option(
//                Option::TYPE_CHOOSE,
//                'Image',
//                null,
//                function () {
//                    $aOptions = [];
//                    foreach (static::IMAGES as $aImage) {
//                        $aOptions[] = $aImage['label'];
//                    }
//                    return $aOptions;
//                }
//            ),
        ];
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

    private function getKeywords(array $aOptions): array
    {
        $aKeywords   = explode(',', $aOptions['KEYWORDS']->getValue());
        $aKeywords[] = static::ENVIRONMENTS[$aOptions['ENVIRONMENT']->getValue()];
        $aKeywords[] = static::IMAGES[$aOptions['IMAGE']->getValue()]['label'];
        $aKeywords[] = static::FRAMEWORKS[$aOptions['FRAMEWORK']->getValue()];

        return array_values(
            array_filter(
                array_unique(
                    array_map(
                        function ($sKeyword) {
                            return strtolower(trim($sKeyword));
                        },
                        $aKeywords
                    )
                )
            )
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Create the server
     *
     * @param string $sDomain  The configured domain name
     * @param array  $aOptions The configured options
     *
     * @return Server
     */
    public function create(string $sDomain, array $aOptions): Server
    {
        $sEnvironment = static::ENVIRONMENTS[$aOptions['ENVIRONMENT']->getValue()];
        $aImage       = static::IMAGES[$aOptions['IMAGE']->getValue()];
        $sFramework   = static::FRAMEWORKS[$aOptions['FRAMEWORK']->getValue()];
        $oRegion      = $this->aRegions[$aOptions['REGION']->getValue()];
        $sSize        = static::SIZES[$aOptions['SIZE']->getValue()]['slug'];

        $aData = [
            'name'              => implode(
                '-',
                array_map(
                    function ($sBit) {
                        return preg_replace('/[^a-z0-9\-]/', '', str_replace('.', '-', strtolower($sBit)));
                    },
                    [
                        $sDomain,
                        $aImage['label'],
                        $sEnvironment,
                        $sFramework,
                    ]
                )
            ),
            'region'            => $oRegion->slug,
            'size'              => $sSize,
            'image'             => $aImage['slug'],
            'backups'           => false,
            'ipv6'              => false,
            'privateNetworking' => false,
            //  @todo (Pablo - 2019-02-07) - Fetch local key + global keys from account
            'sshKeys'           => [],
            'userData'          => '',
            'monitoring'        => true,
            'volumes'           => [],
            'tags'              => $this->getKeywords($aOptions),
            'wait'              => true,
        ];

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

        $oServer = new Server();
        $oServer->setId($oDroplet->id);
        $oServer->setIp($oDroplet->networks[0]->ipAddress);

        return $oServer;
    }

    // --------------------------------------------------------------------------

    /**
     * Destroy the server
     */
    public function destroy(): void
    {
    }
}
