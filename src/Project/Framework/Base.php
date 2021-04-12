<?php

namespace Shed\Cli\Project\Framework;

use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Helper\System;
use Symfony\Component\Yaml\Yaml;

abstract class Base
{
    /**
     * Updates the contents of docker/webserver/Dockerfile to use the specified tag
     *
     * @param string $sPath       The path where the project is being installed
     * @param string $sDesiredTag The desired Docker tag
     *
     * @return $this
     */
    protected function configureDockerFile(string $sPath, string $sDesiredTag): Base
    {
        $sDockerFile = implode(DIRECTORY_SEPARATOR, [
            rtrim($sPath, DIRECTORY_SEPARATOR),
            'docker',
            'webserver',
            'Dockerfile',
        ]);

        $sConfig = file_get_contents($sDockerFile);
        $sConfig = preg_replace('/^FROM (.+):.*$/', 'FROM $1:' . $sDesiredTag, $sConfig);

        file_put_contents($sDockerFile, $sConfig);

        return $this;
    }
}
