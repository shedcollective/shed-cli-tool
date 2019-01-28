<?php

namespace Shed\Cli\Project\Framework\Frontend;

use Shed\Cli\Interfaces\Framework;
use Shed\Cli\Project\Framework\Base;

final class None extends Base implements Framework
{
    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getName()
    {
        return 'None';
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
     * @param Framework $oBackendFramework The other framework
     *
     * @return array
     */
    public function getEnvVars(Framework $oBackendFramework)
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
     */
    public function install($sPath, array $aOptions, Framework $oOtherFramework, array $aInstallOptions)
    {
        //  Silence is golden
    }
}
