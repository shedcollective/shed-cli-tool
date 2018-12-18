<?php

namespace Shed\Cli\Interfaces;

interface Framework
{
    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getName();

    // --------------------------------------------------------------------------

    /**
     * The configurable options for the framework
     *
     * @return array
     */
    public function getOptions();

    // --------------------------------------------------------------------------

    /**
     * Install the framework
     *
     * @param string $sPath    The absolute directory to install the framework to
     * @param array  $aOptions The result of any options
     *
     * @return void
     */
    public function install($sPath, array $aOptions = []);
}
