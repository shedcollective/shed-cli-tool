<?php

namespace Shed\Cli\Project\Framework\Backend;

use Shed\Cli\Command\Project\Create;
use Shed\Cli\Helper\System;
use Shed\Cli\Helper\Debug;
use Shed\Cli\Interfaces\Framework;
use Shed\Cli\Project\Framework\Base;
use Symfony\Component\Yaml\Yaml;

final class Nails extends Base implements Framework
{
    /**
     * The URL of the app skeleton
     *
     * @var string
     */
    const APP_SKELETON = 'https://github.com/shedcollective/skeleton-app/archive/master.zip';

    // --------------------------------------------------------------------------

    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getName()
    {
        return 'Nails';
    }

    // --------------------------------------------------------------------------

    /**
     * The configurable options for the framework
     *
     * @return array
     */
    public function getOptions()
    {
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Returns any ENV vars for the project
     *
     * @param Framework $oFrontendFramework The frontend framework
     *
     * @return array
     */
    public function getEnvVars(Framework $oFrontendFramework)
    {
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Install the framework
     *
     * @param string    $sPath           The absolute directory to install the framework to
     * @param array     $aOptions        The result of any options
     * @param Framework $oOtherFramework The other framework being installed
     * @param array     $aInstallOptions The install options
     *
     * @return void
     */
    public function install($sPath, array $aOptions, Framework $oOtherFramework, array $aInstallOptions)
    {
        $this
            ->configureDockerFile($sPath, 'apache-nails-php72')
            ->installAppSkeleton($sPath)
            ->generatePrivateKey($sPath)
            ->updateAppConfigConstant($sPath, 'APP_NAME', $aInstallOptions['name'])
            ->updateAppConfigConstant($sPath, 'APP_KEY', $this->generateRandomString());
    }

    // --------------------------------------------------------------------------

    /**
     * Install the Shed flavour of the Nails skeleton
     *
     * @param string $sPath The absolute directory where the framework is being installed
     *
     * @return $this
     */
    private function installAppSkeleton($sPath)
    {
        $aArguments = [
            '--dir="' . $sPath . 'www"',
            '--app-skeleton="' . static::APP_SKELETON . '"',
            '--no-docker',
        ];
        System::exec('nails new ' . implode(' ', $aArguments));

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Generate a private key and save it as an environment variable
     *
     * @param string $sPath The absolute directory where the framework is being installed
     *
     * @return $this
     */
    private function generatePrivateKey($sPath)
    {
        Create::updateWebserverEnvVars(
            $sPath,
            ['PRIVATE_KEY' => $this->generateRandomString()]
        );
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Generate a random string
     *
     * @return string
     */
    private function generateRandomString($iLength = 32)
    {
        return bin2hex(random_bytes($iLength));
    }

    // --------------------------------------------------------------------------

    /**
     * Update config/app.php
     *
     * @return string
     */
    private function updateAppConfigConstant($sPath, $sConstant, $sValue)
    {
        $sConfigPath = $sPath . 'www/config/app.php';
        $sConfig     = file_get_contents($sConfigPath);
        $sConfig     = str_replace(
            "define('" . $sConstant . "', '');",
            "define('" . $sConstant . "', '" . str_replace("'", "\'", $sValue) . "');",
            $sConfig
        );

        file_put_contents($sConfigPath, $sConfig);
        return $this;
    }
}
