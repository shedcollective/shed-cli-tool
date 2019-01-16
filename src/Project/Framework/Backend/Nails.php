<?php

namespace Shed\Cli\Project\Framework\Backend;

use Shed\Cli\Interfaces\Framework;
use Shed\Cli\Project\Framework\Base;
use Shed\Cli\Helper\System;

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
     *
     * @return void
     */
    public function install($sPath, array $aOptions, Framework $oOtherFramework)
    {
        $this
            ->configureDockerFile($sPath, 'apache-nails-php72');

        //  Install Nails; done manually so we can use the Shed flavour of the skeleton
        $aArguments = [
            '--dir="' . $sPath . 'www"',
            '--app-skeleton="' . static::APP_SKELETON . '"',
            '--no-docker',
        ];
        System::exec('nails new ' . implode(' ', $aArguments));
    }
}
