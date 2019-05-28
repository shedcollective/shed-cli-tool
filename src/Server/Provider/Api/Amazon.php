<?php

namespace Shed\Cli\Server\Provider\Api;

use Aws\Ec2\Ec2Client;
use Aws\Sts\StsClient;
use Shed\Cli\Entity\Provider\Account;

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

        $this->oApi = new Ec2Client([
            'version'     => 'latest',
            'region'      => 'eu-west-1',
            'credentials' => [
                'key'    => $this->oAccount->getLabel(),
                'secret' => $this->oAccount->getToken(),
            ],
        ]);
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
     */
    public function getApi(): Ec2Client
    {
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
