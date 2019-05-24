<?php

namespace Shed\Cli\Service;

use Exception;
use Shed\Cli\Entity\Server;

/**
 * Class ShedApi
 *
 * @package Shed\Cli\Service
 */
final class ShedApi
{
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
                CURLOPT_URL            => 'https://localhost/api/auth/me',
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
     * @param Server $oServer The server to create
     */
    public static function createServer(Server $oServer)
    {
        //  @todo (Pablo - 2019-05-24) - Authenticated ost request to https://shedcollectiv.com/api/app/server
    }
}
