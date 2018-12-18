<?php

namespace Shed\Cli\Project\Framework\Backend;

use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Helper\System;
use Shed\Cli\Interfaces\Framework;

final class Nails implements Framework
{
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
     * Install the framework
     *
     * @param string $sPath    The absolute directory to install the framework to
     * @param array  $aOptions The result of any options
     *
     * @return void
     * @throws CommandFailedException
     */
    public function install($sPath, array $aOptions = [])
    {
        static::configureDockerFile($sPath, 'apache-nails-php72');
        static::configureDockerEnvironmentVariables($sPath, []);
        static::installFramework($sPath, 'apache-nails-php72');
    }

    // --------------------------------------------------------------------------

    /**
     * Re-writes the docker-compose.yml file, replacing the webserver build definition
     *
     * @param string $sPath              The path where the project is being installed
     * @param string $sDesiredDockerFile The desired webserver Dockerfile
     */
    public static function configureDockerFile($sPath, $sDesiredDockerFile)
    {
        $sDockerComposePath = $sPath . 'docker-compose.yml';
        $sConfig            = file_get_contents($sPath . 'docker-compose.yml');
        $sConfig            = preg_replace(
            '/build: "docker\/webserver\/.*?"/',
            'build: "docker/webserver/' . $sDesiredDockerFile,
            $sConfig
        );
        file_put_contents($sDockerComposePath, $sConfig);
    }

    // --------------------------------------------------------------------------

    public static function configureDockerEnvironmentVariables($sPath, array $aVariables)
    {
        //  @todo (Pablo - 2018-12-18) -
    }

    // --------------------------------------------------------------------------

    /**
     * Installs the framework
     *
     * @param string $sPath              The path where the project is being installed
     * @param string $sDesiredDockerFile The name of the Dockerfile where the install-framework.sh file is located
     *
     * @throws CommandFailedException
     */
    public static function installFramework($sPath, $sDesiredDockerFile)
    {
        System::exec([
            'cd "' . $sPath . '"',
            'mkdir -p www',
            './docker/webserver/' . $sDesiredDockerFile . '/templates/install-framework.sh',
        ]);
    }
}
