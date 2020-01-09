<?php

namespace Shed\Cli\Server\Provider\Api;

use Aws\Ec2\Ec2Client;
use Aws\Sts\StsClient;
use Shed\Cli\Entity\Provider\Account;
use Shed\Cli\Helper\Debug;

final class Amazon
{
    /**
     * The account to use
     *
     * @var Account
     */
    private $oAccount;

    /**
     * The Digital Ocean API
     *
     * @var Ec2Client
     */
    private $oApi;

    // --------------------------------------------------------------------------

    /**
     * Auth constructor.
     *
     * @param Account $oAccount The account to use
     */
    public function __construct($oAccount)
    {
        $this->oAccount = $oAccount;
    }

    // --------------------------------------------------------------------------

    /**
     * Return the account being used
     *
     * @return Account
     */
    public function getAccount(): Account
    {
        return $this->oAccount;
    }

    // --------------------------------------------------------------------------

    /**
     * Return the digital Ocean API
     *
     * @param string $sRegion  The region to use
     * @param string $sVersion The version to use
     *
     * @return Ec2Client
     */
    public function getApi($sRegion = 'eu-west-1', $sVersion = 'latest'): Ec2Client
    {
        if (empty($this->oApi)) {
            $this->oApi = new Ec2Client([
                'version'     => $sVersion,
                'region'      => $sRegion,
                'credentials' => [
                    'key'    => $this->oAccount->getLabel(),
                    'secret' => $this->oAccount->getToken(),
                ],
            ]);
        }

        return $this->oApi;
    }

    // --------------------------------------------------------------------------

    /**
     * Test the connection
     *
     * @param string $sAccessKey    The access key to test
     * @param string $sAccessSecret The access secret to test
     */
    public static function test(string $sAccessKey, string $sAccessSecret)
    {
        $client = new StsClient([
            'version'     => 'latest',
            'region'      => 'eu-west-1',
            'credentials' => [
                'key'    => $sAccessKey,
                'secret' => $sAccessSecret,
            ],
        ]);

        $client->getCallerIdentity();
    }
}
