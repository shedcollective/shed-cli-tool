<?php

namespace App\Project\Framework\Backend;

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
     */
    public function install($sPath)
    {
        static::configureDockerFile($sPath, 'apache-nails-php72');
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
}
