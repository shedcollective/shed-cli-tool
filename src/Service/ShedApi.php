<?php

namespace Shed\Cli\Service;

use Exception;
use Shed\Cli\Entity\Provider\Account;
use Shed\Cli\Entity\Server;

/**
 * Class ShedApi
 *
 * @package Shed\Cli\Service
 */
final class ShedApi
{
    /**
     * The API Endpoint
     *
     * @var string
     */
    const API_ENDPOINT = 'https://shedcollective.com/api/';

    // --------------------------------------------------------------------------

    /**
     * Tests a token is valid
     *
     * @param string $sToken The token to test
     *
     * @throws Exception
     */
    public static function testToken(string $sToken)
    {
        $oCurl = curl_init();

        curl_setopt_array(
            $oCurl,
            [
                CURLOPT_URL            => static::API_ENDPOINT . 'auth/me',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => 'utf-8',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_HTTPHEADER     => [
                    'X-Access-Token: ' . $sToken,
                    'cache-control: no-cache',
                ],
            ]
        );

        curl_exec($oCurl);

        $sError = curl_error($oCurl);
        $iCode  = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);

        curl_close($oCurl);

        if ($sError) {
            throw new Exception($sError);
        } elseif ($iCode !== 200) {
            throw new Exception('Invalid access token');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new server on the Shed website
     *
     * @param Account $oAccount The shedcollective.com account to authenticate with
     * @param Server  $oServer  The server to create
     *
     * @throws Exception
     */
    public static function createServer(Account $oAccount, Server $oServer)
    {
        $oCurl = curl_init();

        $sData = json_encode([
            'label'       => $oServer->getLabel(),
            'instance_id' => $oServer->getId(),
            'ip'          => $oServer->getIp(),
            'domains'     => [
                ['domain' => $oServer->getDomain()],
            ],
        ]);

        curl_setopt_array(
            $oCurl,
            [
                CURLOPT_URL            => static::API_ENDPOINT . 'app/server',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => 'utf-8',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => $sData,
                CURLOPT_HTTPHEADER     => [
                    'X-Access-Token: ' . $oAccount->getToken(),
                    'cache-control: no-cache',
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($sData),
                ],
            ]
        );

        curl_exec($oCurl);

        $sError = curl_error($oCurl);
        $iCode  = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);

        curl_close($oCurl);

        if ($sError) {
            throw new Exception($sError);
        } elseif ($iCode !== 200) {
            throw new Exception('Invalid access token');
        }
    }
}
