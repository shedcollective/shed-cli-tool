<?php

namespace Shed\Cli\Project\Framework\Backend;

use Shed\Cli\Command\Project\Create;
use Shed\Cli\Entity\Option;
use Shed\Cli\Helper\System;
use Shed\Cli\Interfaces\Framework;
use Shed\Cli\Project\Framework\Base;

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
    public function getLabel(): string
    {
        return 'Nails';
    }

    // --------------------------------------------------------------------------

    /**
     * The configurable options for the framework
     *
     * @return Option[]
     */
    public function getOptions(): array
    {
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Returns any ENV vars for the project
     *
     * @param Framework $oFrontendFramework The frontend framework
     * @param array     $aInstallOptions    The install options
     *
     * @return array
     * @throws \Exception
     */
    public function getEnvVars(Framework $oFrontendFramework, array $aInstallOptions): array
    {
        return [
            'APP_NAME' => $aInstallOptions['name'],
            'APP_KEY'  => $this->generateRandomString(),
        ];
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
     * @throws \Exception
     */
    public function install($sPath, array $aOptions, Framework $oOtherFramework, array $aInstallOptions): void
    {
        $this
            ->configureDockerFile($sPath, 'apache-nails-php72')
            ->installAppSkeleton($sPath)
            ->generatePrivateKey($sPath);
    }

    // --------------------------------------------------------------------------

    /**
     * Install the Shed flavour of the Nails skeleton
     *
     * @param string $sPath The absolute directory where the framework is being installed
     *
     * @return $this
     */
    private function installAppSkeleton($sPath): Nails
    {
        $aArguments = [
            '--dir="' . $sPath . 'www"',
            '--app-skeleton="' . static::APP_SKELETON . '"',
            '--no-docker',
        ];
        System::exec('nails new:project ' . implode(' ', $aArguments));

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Generate a private key and save it as an environment variable
     *
     * @param string $sPath The absolute directory where the framework is being installed
     *
     * @return $this
     * @throws \Exception
     */
    private function generatePrivateKey($sPath): Nails
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
     * @param int $iLength The desired length of the random string
     *
     * @return string
     * @throws \Exception
     */
    private function generateRandomString($iLength = 32): string
    {
        return bin2hex(random_bytes($iLength));
    }

    // --------------------------------------------------------------------------

    /**
     * Update config/app.php
     *
     * @param string $sPath     The path to the installation
     * @param string $sConstant The constant to set
     * @param string $sValue    The value to set
     *
     * @return $this
     */
    private function updateAppConfigConstant($sPath, $sConstant, $sValue): Nails
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
