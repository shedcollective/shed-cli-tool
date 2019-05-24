<?php

namespace Shed\Cli\Command\Auth;

use Exception;
use Shed\Cli\Command\Auth;
use Shed\Cli\Helper\Debug;
use Shed\Cli\Server\Provider\Api;

final class Shed extends Auth
{
    /**
     * The third party slug
     *
     * @var string
     */
    const SLUG = 'shed';

    /**
     * The third party name
     *
     * @var string
     */
    const LABEL = 'Shed';

    // --------------------------------------------------------------------------

    /**
     * Show help on how to generate credentials
     */
    protected function help(): void
    {
        $this->oOutput->writeln('');
        $this->oOutput->writeln('To generate a new Personal Access Token:');
        $this->oOutput->writeln('');
        $this->oOutput->writeln('<comment>1:</comment> Get your Personal Access Token here: <comment>https://cloud.digitalocean.com/account/api/tokens</comment>');
        $this->oOutput->writeln('<comment>2:</comment> Run: <comment>shed auth:shed</comment>');
        $this->oOutput->writeln('<comment>3:</comment> Specify a label for the account');
        $this->oOutput->writeln('<comment>4:</comment> Specify the access token');
        $this->oOutput->writeln('');
    }

    // --------------------------------------------------------------------------

    /**
     * Verify a token is valid
     *
     * @param string $sToken The token to validate
     *
     * @throws Exception
     */
    protected function testToken(string $sToken): void
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
}
