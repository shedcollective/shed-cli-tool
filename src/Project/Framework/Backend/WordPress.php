<?php

namespace Shed\Cli\Project\Framework\Backend;

use Shed\Cli\Interfaces\Framework;
use Shed\Cli\Project\Framework\Base;

final class WordPress extends Base implements Framework
{
    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getName(): string
    {
        return 'WordPress';
    }

    // --------------------------------------------------------------------------

    /**
     * The configurable options for the framework
     *
     * @return array
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
     *
     * @return array
     */
    public function getEnvVars(Framework $oFrontendFramework): array
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
     *
     * @return void
     */
    public function install($sPath, array $aOptions, Framework $oOtherFramework, array $aInstallOptions): void
    {
        $this
            ->configureDockerFile($sPath, 'apache-wordpress-php72')
            ->installFramework($sPath, 'apache-wordpress-php72');
    }
}
