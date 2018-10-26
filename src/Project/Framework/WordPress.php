<?php

namespace App\Project\Framework;

use App\Exceptions\CommandFailed;
use App\Interfaces\Framework;

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
     * Install the framework
     *
     * @param string The absolute directory to install the framework to
     *
     * @return void
     * @throws CommandFailed
     */
    public function install($sPath)
    {
        Nails::configureDockerFile($sPath, 'apache-wordpress-php72');
        Nails::installFramework($sPath, 'apache-wordpress-php72', 'shedcollective/frontend-wordpress');
    }
}
