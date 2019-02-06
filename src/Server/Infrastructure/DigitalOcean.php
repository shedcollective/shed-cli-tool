<?php

namespace Shed\Cli\Server\Infrastructure;

use Shed\Cli\Exceptions\CliException;
use Shed\Cli\Helper\Debug;
use Shed\Cli\Interfaces\Infrastructure;
use Shed\Cli\Resources\Option;
use Shed\Cli\Server\Infrastructure\Api;

final class DigitalOcean extends Base implements Infrastructure
{
    const DIGITAL_OCEAN_IMAGES = [
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

    const DIGITAL_OCEAN_SIZES = [
        [
            'slug'  => '1gb',
            'label' => '1Gb',
        ],
        [
            'slug'  => '2gb',
            'label' => '2Gb',
        ],
        [
            'slug'  => '4gb',
            'label' => '4Gb',
        ],
        [
            'slug'  => '8gb',
            'label' => '8Gb',
        ],
        [
            'slug'  => '16gb',
            'label' => '16Gb',
        ],
        [
            'slug'  => '32gb',
            'label' => '32Gb',
        ],
    ];

    // --------------------------------------------------------------------------

    /**
     * The digital ocean API
     *
     * @var Api\DigitalOcean
     */
    private $oDigitalOcean;
    private $aRegions;
    private $aSizes;

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
            'REGION'  => new Option(
                Option::TYPE_CHOOSE,
                'Region',
                null,
                function (array $aOptions) {

                    if (empty($this->aRegions)) {
                        $oAccount            = Api\DigitalOcean::getAccountByIndex($aOptions['ACCOUNT']->getValue());
                        $this->oDigitalOcean = new Api\DigitalOcean($oAccount->token);
                        $this->aRegions      = $this->oDigitalOcean->getRegionApi()->getAll();
                    }

                    $aOptions = [];
                    foreach ($this->aRegions as $oRegion) {
                        if ($oRegion->available) {
                            $aOptions[] = $oRegion->name;
                        }
                    }

                    return $aOptions;
                }
            ),
            'MEMORY'  => new Option(
                Option::TYPE_CHOOSE,
                'Memory',
                null,
                function () {
                    $aOptions = [];
                    foreach (static::DIGITAL_OCEAN_SIZES as $aSize) {
                        $aOptions[] = $aSize['label'];
                    }
                    return $aOptions;
                }
            ),
            'IMAGE'   => new Option(
                Option::TYPE_CHOOSE,
                'Image',
                null,
                function () {
                    $aOptions = [];
                    foreach (static::DIGITAL_OCEAN_IMAGES as $aImage) {
                        $aOptions[] = $aImage['label'];
                    }
                    return $aOptions;
                }
            ),
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

    /**
     * Create the server
     *
     * @param string $sDomain  The configured domain name
     * @param array  $aOptions The configured options
     */
    public function create(string $sDomain, array $aOptions): void
    {
        $this->oOutput->writeln('');
        $this->oOutput->writeln('🚧 Deploying DigitalOcean servers is a work in progress');
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
