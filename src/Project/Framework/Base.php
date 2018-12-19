<?php

namespace Shed\Cli\Project\Framework;

use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Helper\System;

abstract class Base
{
    /**
     * Re-writes the docker-compose.yml file, replacing the web server build definition
     *
     * @param string $sPath              The path where the project is being installed
     * @param string $sDesiredDockerFile The desired web server Dockerfile
     *
     * @return $this
     */
    protected function configureDockerFile($sPath, $sDesiredDockerFile)
    {
        $sDockerComposePath = $sPath . 'docker-compose.yml';
        $sConfig            = file_get_contents($sPath . 'docker-compose.yml');
        $sConfig            = preg_replace(
            '/build: "docker\/webserver\/.*?"/',
            'build: "docker/webserver/' . $sDesiredDockerFile,
            $sConfig
        );
        file_put_contents($sDockerComposePath, $sConfig);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Installs the framework
     *
     * @param string $sPath              The path where the project is being installed
     * @param string $sDesiredDockerFile The name of the Dockerfile where the install-framework.sh file is located
     *
     * @return $this
     * @throws CommandFailedException
     */
    protected function installFramework($sPath, $sDesiredDockerFile)
    {
        System::exec([
            'cd "' . $sPath . '"',
            'mkdir -p www',
            $sDesiredDockerFile ? './docker/webserver/' . $sDesiredDockerFile . '/templates/install-framework.sh' : null,
        ]);

        return $this;
    }
}
