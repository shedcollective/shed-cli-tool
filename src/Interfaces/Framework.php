<?php

namespace App\Interfaces;

interface Framework
{
    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getName();

    /**
     * Install the framework
     *
     * @param string The absolute directory to install the framework to
     *
     * @return void
     */
    public function install($sPath);
}
