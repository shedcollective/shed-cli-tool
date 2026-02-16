<?php

namespace Shed\Cli\Server\Provider;

use Aws\Ec2\Ec2Client;
use phpseclib3\Crypt\EC;
use Shed\Cli\Command\Auth;
use Shed\Cli\Entity;
use Shed\Cli\Entity\Provider\Account;
use Shed\Cli\Entity\Provider\Image;
use Shed\Cli\Entity\Provider\Region;
use Shed\Cli\Entity\Provider\Size;
use Shed\Cli\Exceptions\CliException;
use Shed\Cli\Exceptions\Server\TimeoutException;
use Shed\Cli\Interfaces;
use Shed\Cli\Server;

final class Amazon extends Server\Provider implements Interfaces\Provider
{
    /**
     * The default region to use (London)
     */
    const DEFAULT_REGION = 'eu-west-2';

    const SECURITY_GROUPS = [
        'sg-005929c5b74dfdd83', //  HTTP(S)
        'sg-0fad58178b3d69e60', //  Shed VPN
    ];

    /**
     * Human friendly names of AWS regions
     *
     * @var array
     */
    const REGION_HUMAN = [
        'us-east-1'      => 'US East (N. Virginia)',
        'us-east-2'      => 'US East (Ohio)',
        'us-west-1'      => 'US West (N. California)',
        'us-west-2'      => 'US West (Oregon)',
        'af-south-1'     => 'Africa (Cape Town)',
        'ap-east-1'      => 'Asia Pacific (Hong Kong)',
        'ap-south-2'     => 'Asia Pacific (Hyderabad)',
        'ap-southeast-3' => 'Asia Pacific (Jakarta)',
        'ap-southeast-5' => 'Asia Pacific (Malaysia)',
        'ap-southeast-4' => 'Asia Pacific (Melbourne)',
        'ap-south-1'     => 'Asia Pacific (Mumbai)',
        'ap-northeast-3' => 'Asia Pacific (Osaka)',
        'ap-northeast-2' => 'Asia Pacific (Seoul)',
        'ap-southeast-1' => 'Asia Pacific (Singapore)',
        'ap-southeast-2' => 'Asia Pacific (Sydney)',
        'ap-east-2'      => 'Asia Pacific (Taipei)',
        'ap-southeast-7' => 'Asia Pacific (Thailand)',
        'ap-northeast-1' => 'Asia Pacific (Tokyo)',
        'ca-central-1'   => 'Canada (Central) ',
        'ca-west-1'      => 'Canada West (Calgary)',
        'eu-central-1'   => 'Europe (Frankfurt) ',
        'eu-west-1'      => 'Europe (Ireland) ',
        'eu-west-2'      => 'Europe (London) ',
        'eu-south-1'     => 'Europe (Milan) ',
        'eu-west-3'      => 'Europe (Paris) ',
        'eu-south-2'     => 'Europe (Spain) ',
        'eu-north-1'     => 'Europe (Stockholm) ',
        'eu-central-2'   => 'Europe (Zurich) ',
        'il-central-1'   => 'Israel (Tel Aviv)',
        'mx-central-1'   => 'Mexico (Central) ',
        'me-south-1'     => 'Middle East (Bahrain)',
        'me-central-1'   => 'Middle East (UAE)',
        'sa-east-1'      => 'South America (São Paulo)',

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
        $this->fetchImages($oAccount);
        $aOut = [];
        foreach ($this->aImages as $aImage) {
            $aOut[$aImage['Name']] = new Image($aImage['Name'], $aImage['ImageId']);
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

        //  Add key to AWS temporarily
        $sKeyName = 'temp-key-' . uniqid();
        $this
            ->getApi($oAccount)
            ->importKeyPair([
                'KeyName'           => $sKeyName,
                'PublicKeyMaterial' => $oRootKey->getPublicKey()->toString('OpenSSH'),
            ]);

        // Prepare cloud-init user-data for the 'ubuntu' user
        $sPublicKey = $oRootKey->getPublicKey()->toString('OpenSSH');
        $sUserData  = <<<YAML
            #cloud-config
            users:
              - name: ubuntu
                ssh-authorized-keys:
                  - {$sPublicKey}
            YAML;

        $oResult = $this
            ->getApi($oAccount)
            ->runInstances([
                'ImageId'           => $oImage->getSlug(),
                'InstanceType'      => $oSize->getSlug(),
                'MinCount'          => 1,
                'MaxCount'          => 1,
                'KeyName'           => $sKeyName,
                'UserData'          => base64_encode($sUserData),
                'SecurityGroupIds'  => self::SECURITY_GROUPS,
                'TagSpecifications' => [
                    [
                        'ResourceType' => 'instance',
                        'Tags'         => [
                            [
                                'Key'   => 'Name',
                                'Value' => $sHostname,
                            ],
                            [
                                'Key'   => 'Environment',
                                'Value' => $sEnvironment,
                            ],
                            [
                                'Key'   => 'Framework',
                                'Value' => $sFramework,
                            ],
                            [
                                'Key'   => 'Image',
                                'Value' => $oImage->getSlug(),
                            ],
                            [
                                'Key'   => 'Keywords',
                                'Value' => implode(',', $aKeywords),
                            ],
                        ],
                    ],
                ],
            ]);

        $aInstance = $oResult->get('Instances')[0];
        $aInstance = $this->waitForInstanceToBeActive($oAccount, $aInstance, 300);

        //  Remove the temporary key
        $this
            ->getApi($oAccount)
            ->deleteKeyPair([
                'KeyName' => $sKeyName,
            ]);

        return (new Entity\Server())
            ->setLabel($sHostname)
            ->setSlug($sHostname)
            ->setId($aInstance['InstanceId'])
            ->setIp($aInstance['PublicIpAddress'])
            ->setDomain($sDomain)
            ->setHostname($sHostname)
            ->setDisk(
                new Entity\Provider\Disk(
                    $aInstance['BlockDeviceMappings'][0]['Ebs']['VolumeId'],
                    $aInstance['BlockDeviceMappings'][0]['Ebs']['VolumeId']
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
     * Fetch and cache regions from AWS
     *
     * @param Account $oAccount The account to use
     */
    private function fetchRegions(Account $oAccount)
    {
        if (empty($this->aRegions)) {
            $this->aRegions = $this
                ->getApi($oAccount)
                ->describeRegions()
                ->get('Regions');
        }
    }


    // --------------------------------------------------------------------------

    /**
     * Fetch and cache Images from AWS
     *
     * @param Account $oAccount The account to use
     */
    private function fetchImages(Account $oAccount)
    {
        if (empty($this->aImages)) {
            $this->aImages = $this
                ->getApi($oAccount)
                ->describeImages(['Owners' => ['self']])
                ->get('Images');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the AWS API
     *
     * @param Account $oAccount The account to use
     *
     * @return Ec2Client
     */
    private function getApi(Account $oAccount): Ec2Client
    {
        if (empty($this->oAmazon)) {
            $this->oAmazon = new Api\Amazon($oAccount);
        }

        return $this->oAmazon->getApi();
    }

    // --------------------------------------------------------------------------

    /**
     * Waits for the instance to become active
     *
     * @param Account  $oAccount
     * @param array    $aInstance
     * @param int|null $iTimeout
     *
     * @return array
     */
    private function waitForInstanceToBeActive(Account $oAccount, array $aInstance, ?int $iTimeout = null): array
    {
        $iEndTime = time() + ($iTimeout ?? 300);

        while (time() < $iEndTime) {

            sleep(min(20, $iEndTime - time()));

            $aStatuses = $this
                ->getApi($oAccount)
                ->describeInstanceStatus([
                    'InstanceIds' => [
                        $aInstance['InstanceId'],
                    ],
                ])
                ->get('InstanceStatuses');

            if (
                $aStatuses[0]['InstanceState']['Name'] == 'running'
                && $aStatuses[0]['SystemStatus']['Status'] == 'ok'
                && $aStatuses[0]['InstanceStatus']['Status'] == 'ok'
            ) {
                $aResult = $this
                    ->getApi($oAccount)
                    ->describeInstances([
                        'InstanceIds' => [
                            $aInstance['InstanceId'],
                        ],
                    ]);

                return $aResult['Reservations'][0]['Instances'][0];
            }
        }

        throw new TimeoutException(sprintf(
            'Timed-out whilst waiting for instance %s to become active.',
            $aInstance['InstanceId']
        ));
    }
}
