<?php

namespace App\Project\Framework\Backend;

use App\Interfaces\Framework;

final class Laravel implements Framework
{
    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getName()
    {
        return 'Laravel';
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
        Nails::configureDockerFile($sPath, 'apache-laravel-php72');
    }
}
