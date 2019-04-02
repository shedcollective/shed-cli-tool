<?php

namespace Shed\Cli\Server\Provider\Api;

use Shed\Cli\Entity\Provider\Account;
use Shed\Cli\Exceptions\Auth\AccountNotFoundException;
use Shed\Cli\Helper\Debug;
use Shed\Cli\Helper\Directory;
use Shed\Cli\Helper\Updates;

final class GoogleCloud
{
    /**
     * The account to use
     *
     * @var Account
     */
    private $oAccount;

    /**
     * The Google Cloud API Client
     *
     * @var \Google_Client
     */
    private $oClient;

    /**
     * The Google Cloud Compute API
     *
     * @var \Google_Service_Compute
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

        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $oAccount->getToken());

        $this->oClient = new \Google_Client();
        $this->oClient->setApplicationName('Shed CLI Tool ' . Updates::getCurrentVersion());
        $this->oClient->useApplicationDefaultCredentials();
        $this->oClient->addScope(\Google_Service_Compute::COMPUTE);

        $this->oApi = new \Google_Service_Compute($this->oClient);
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
     * Return the Google Cloud Client
     *
     * @return \Google_Client
     */
    public function getClient(): \Google_Client
    {
        return $this->oClient;
    }

    // --------------------------------------------------------------------------

    /**
     * Return the Google Cloud Compute API
     *
     * @return \Google_Service_Compute
     */
    public function getApi(): \Google_Service_Compute
    {
        return $this->oApi;
    }

    // --------------------------------------------------------------------------

    /**
     * Test the connection
     *
     * @param string $sToken The token to test
     */
    public static function test(string $sToken)
    {
        $sKeyPath = Directory::resolvePath($sToken);
        if (!is_file($sKeyPath)) {
            throw new AccountNotFoundException(
                'Key file not found at "' . $sKeyPath . '"'
            );
        }

        //  @todo (Pablo - 2019-02-18) - Validate the connection
    }

    // --------------------------------------------------------------------------

    /**
     * Decode the active key
     *
     * @return \stdClass
     */
    public function getKeyObject(): \stdClass
    {
        return json_decode(file_get_contents($this->oAccount->getToken()));
    }

    // --------------------------------------------------------------------------

    /**
     * Return available regions
     *
     * @return \Google_Service_Compute_RegionList
     */
    public function getRegions(): \Google_Service_Compute_RegionList
    {
        return $this
            ->getApi()
            ->regions
            ->listRegions(
                $this->getKeyObject()
                    ->project_id
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Wait for an operation to complete
     *
     * @param \Google_Service_Compute $oService   The Compute service
     * @param string                  $sProjectId The Project ID
     * @param string                  $sZone      The Zone
     * @param string                  $sOperation The operation to wait for
     *
     * @return bool
     */
    public static function wait(
        \Google_Service_Compute $oService,
        string $sProjectId,
        string $sZone,
        string $sOperation
    ) {
        for ($x = 0; $x <= 20; $x++) {
            $oOperationStatus = $oService->zoneOperations->get($sProjectId, $sZone, $sOperation);
            if ('DONE' == $oOperationStatus->getStatus()) {
                return true;
            }
            sleep((2 * $x));
        }
        return false;
    }
}
