<?php

namespace Shed\Cli\Project\Framework\Frontend;

use Shed\Cli\Entity\Option;
use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Exceptions\Zip\CannotOpenException;
use Shed\Cli\Helper\System;
use Shed\Cli\Helper\Zip;
use Shed\Cli\Interfaces\Framework;
use Shed\Cli\Project\Framework\Backend\Laravel;
use Shed\Cli\Project\Framework\Backend\Nails;
use Shed\Cli\Project\Framework\Backend\None;
use Shed\Cli\Project\Framework\Backend\WordPress;
use Shed\Cli\Project\Framework\Base;

final class Webpack extends Base implements Framework
{
    /**
     * The URL of the Docker skeleton
     *
     * @var string
     */
    const FRONTEND_BOOTSTRAPPER = 'https://github.com/shedcollective/shed-frontend-bootstrapper/archive/master.zip';

    /**
     * The name of the folder within the zip archive
     *
     * @var string
     */
    const FRONTEND_BOOTSTRAPPER_FOLDER = 'shed-frontend-bootstrapper-master';

    /**
     * These files should exist at the root of the project (i.e. ./www)
     *
     * @var array
     */
    const ROOT_FILES = [
        'package.json',
        'webpack.config.js',
        '.eslint',
        '.sass-lint.yml',
    ];

    // --------------------------------------------------------------------------

    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Webpack';
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
     * @param Framework $oBackendFramework The backend framework
     *
     * @return array
     */
    public function getEnvVars(Framework $oBackendFramework): array
    {
        if ($oBackendFramework instanceof Laravel) {
            return [
                'WEBPACK_INPUT_PATH'  => './resources/assets/js/',
                'WEBPACK_OUTPUT_PATH' => './public/',
            ];
        } elseif ($oBackendFramework instanceof Nails) {
            return [
                'WEBPACK_INPUT_PATH'  => './assets/js/',
                'WEBPACK_OUTPUT_PATH' => './assets/build/',
            ];
        } elseif ($oBackendFramework instanceof None) {
            return [
                'WEBPACK_INPUT_PATH'  => './assets/js/',
                'WEBPACK_OUTPUT_PATH' => './assets/build/',
            ];
        } else {
            return [];
        }
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
     * @throws CommandFailedException
     * @throws CannotOpenException
     */
    public function install($sPath, array $aOptions, Framework $oOtherFramework, array $aInstallOptions): void
    {
        //  Clean up target
        $aFiles    = array_merge(static::ROOT_FILES, ['webpack.*.js', 'assets']);
        $aCommands = [];
        foreach ($aFiles as $sFile) {
            $aCommands[] = 'rm -rf ' . $sPath . 'www/' . $sFile;
        }
        System::exec($aCommands);

        //  Download skeleton
        $sZipPath = $sPath . 'webpack.zip';
        file_put_contents($sZipPath, file_get_contents(static::FRONTEND_BOOTSTRAPPER));

        //  Install
        if ($oOtherFramework instanceof Laravel) {
            $this->installLaravel($sZipPath, $sPath);
        } elseif ($oOtherFramework instanceof Nails) {
            $this->installNails($sZipPath, $sPath);
        } elseif ($oOtherFramework instanceof None) {
            $this->installNone($sZipPath, $sPath);
        } elseif ($oOtherFramework instanceof WordPress) {
            $this->installWordPress($sZipPath, $sPath);
        }

        //  Update package.json
        $sPackagePath   = $sPath . 'www/package.json';
        $oPackage       = json_decode(file_get_contents($sPackagePath));
        $oPackage->name = $aInstallOptions['slug'];
        file_put_contents($sPackagePath, json_encode($oPackage, JSON_PRETTY_PRINT));
    }

    // --------------------------------------------------------------------------

    /**
     * Installs for Laravel
     *
     * @param string $sZipPath The path to the Zip
     * @param string $sPath    the path to the project
     */
    private function installLaravel($sZipPath, $sPath): void
    {
        Zip::unzip(
            $sZipPath,
            $sPath . 'www/resources/',
            static::FRONTEND_BOOTSTRAPPER_FOLDER . '/src'
        );

        $aFiles    = array_merge(static::ROOT_FILES);
        $aCommands = [];
        foreach ($aFiles as $sFile) {
            $aCommands[] = 'mv ' . $sPath . 'www/resources/' . $sFile . ' ' . $sPath . 'www/' . $sFile;
        }

        //  Tidy up
        $aCommands[] = 'rm -rf ' . $sPath . 'www/resources' . static::FRONTEND_BOOTSTRAPPER_FOLDER;

        System::exec($aCommands);
    }

    // --------------------------------------------------------------------------

    /**
     * Installs for Nails
     *
     * @param string $sZipPath The path to the Zip
     * @param string $sPath    the path to the project
     */
    private function installNails($sZipPath, $sPath): void
    {
        Zip::unzip(
            $sZipPath,
            $sPath . 'www/',
            static::FRONTEND_BOOTSTRAPPER_FOLDER . '/src'
        );
        System::exec('rm -rf ' . $sPath . 'www/' . static::FRONTEND_BOOTSTRAPPER_FOLDER);
    }

    // --------------------------------------------------------------------------

    /**
     * Installs for None
     *
     * @param string $sZipPath The path to the Zip
     * @param string $sPath    the path to the project
     */
    private function installNone($sZipPath, $sPath): void
    {
        Zip::unzip(
            $sZipPath,
            $sPath,
            static::FRONTEND_BOOTSTRAPPER_FOLDER . '/src'
        );
        System::exec('rm -rf ' . $sPath . 'www/' . static::FRONTEND_BOOTSTRAPPER_FOLDER);
    }

    // --------------------------------------------------------------------------

    /**
     * Installs for WordPress
     *
     * @param string $sZipPath The path to the Zip
     * @param string $sPath    the path to the project
     */
    private function installWordPress($sZipPath, $sPath): void
    {
    }
}
