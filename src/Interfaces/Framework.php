<?php

namespace Shed\Cli\Interfaces;

use Shed\Cli\Entity\Option;

interface Framework
{
    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getLabel(): string;

    // --------------------------------------------------------------------------

    /**
     * The configurable options for the framework
     *
     * @return Option[]
     */
    public function getOptions(): array;

    // --------------------------------------------------------------------------

    /**
     * Returns any ENV vars for the project
     *
     * @param Framework $oOtherFramework The other framework
     *
     * @return array
     */
    public function getEnvVars(Framework $oOtherFramework): array;

    // --------------------------------------------------------------------------

    /**
     * Install the framework
     *
     * @param string    $sPath           The absolute directory to install the framework to
     * @param array     $aOptions        The result of any options
     * @param Framework $oOtherFramework The other framework being installed
     * @param array     $aInstallOptions The install options
     */
    public function install($sPath, array $aOptions, Framework $oOtherFramework, array $aInstallOptions): void;
}
