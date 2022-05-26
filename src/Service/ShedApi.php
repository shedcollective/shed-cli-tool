<?php

namespace Shed\Cli\Service;

use Shed\Cli\Entity\Provider\Account;
use Shed\Cli\Entity\Server;
use Shed\Cli\Exceptions\CliException;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @throws CliException
     */
    public static function testToken(string $sToken, OutputInterface $oOutput = null)
    {
        $oCurl   = curl_init();
        $oOutput = $oOutput ?? new NullOutput();

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

        $sBody  = curl_exec($oCurl);
        $sError = curl_error($oCurl);
        $iCode  = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);

        curl_close($oCurl);

        if ($sError) {

            $oOutput->writeln('', $oOutput::VERBOSITY_VERY_VERBOSE);
            $oOutput->writeln('Curl Code: ' . $iCode, $oOutput::VERBOSITY_VERY_VERBOSE);
            $oOutput->writeln('Curl Error: ' . $sError, $oOutput::VERBOSITY_VERY_VERBOSE);
            $oOutput->writeln('Curl Body: ' . $sBody, $oOutput::VERBOSITY_VERY_VERBOSE);
            throw new CliException($sError);

        } elseif ($iCode !== 200) {

            $oOutput->writeln('', $oOutput::VERBOSITY_VERY_VERBOSE);
            $oOutput->writeln('Curl Code: ' . $iCode, $oOutput::VERBOSITY_VERY_VERBOSE);
            $oOutput->writeln('Curl Body: ' . $sBody, $oOutput::VERBOSITY_VERY_VERBOSE);
            throw new CliException('Invalid access token');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new server on the Shed website
     *
     * @param Account $oAccount The shedcollective.com account to authenticate with
     * @param Server  $oServer  The server to create
     *
     * @throws CliException
     */
    public static function createServer(Account $oAccount, Server $oServer)
    {
        $oCurl = curl_init();

        $sData = json_encode([
            'label'       => $oServer->getLabel(),
            'instance_id' => $oServer->getId(),
            'ip'          => $oServer->getIp(),
            //  @todo (Pablo - 2019-08-02) - More details, like is_backups_enabled etc
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
            throw new CliException($sError);
        } elseif ($iCode !== 200) {
            throw new CliException('Invalid access token');
        }
    }
}
