<?php

namespace Shed\Cli\Project\Framework\Backend;

use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Interfaces\Framework;

final class WordPress implements Framework
{
    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getName()
    {
        return 'WordPress';
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
        Nails::configureDockerFile($sPath, 'apache-wordpress-php72');
        Nails::configureDockerEnvironmentVariables($sPath, []);
        Nails::installFramework($sPath, 'apache-wordpress-php72');
    }
}
