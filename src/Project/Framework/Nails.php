<?php

namespace App\Project\Framework;

use App\Exceptions\CommandFailed;
use App\Helper\System;
use App\Interfaces\Framework;

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
     * Install the framework
     *
     * @param string The absolute directory to install the framework to
     *
     * @return void
     * @throws CommandFailed
     */
    public function install($sPath)
    {
        static::configureDockerFile($sPath, 'apache-nails-php72');
        static::installFramework($sPath, 'apache-nails-php72', 'shedcollective/frontend-nails');
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

    /**
     * Installs the framework
     *
     * @param string $sPath              The path where the project is being installed
     * @param string $sDesiredDockerFile The name of the Dockerfile where the install-framework.sh file is located
     * @param string $sNpmPackage        The name of the NPM package to install the frontend
     *
     * @throws CommandFailed
     */
    public static function installFramework($sPath, $sDesiredDockerFile, $sNpmPackage)
    {
        System::exec([
            'cd "' . $sPath . '"',
            'mkdir -p www',
            './docker/webserver/' . $sDesiredDockerFile . '/templates/install-framework.sh',
            //  @todo (Pablo - 2018-10-27) - Complete this once F/E have configured everything
            //  'npm install ' . $sNpmPackage
        ]);
    }
}
