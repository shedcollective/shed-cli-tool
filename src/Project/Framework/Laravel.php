<?php

namespace App\Project\Framework;

use App\Exceptions\CommandFailed;
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
     * @throws CommandFailed
     */
    public function install($sPath)
    {
        //  @todo (Pablo - 2018-10-27) - This still requires some work
        Nails::configureDockerFile($sPath, 'apache-laravel-php72');
        Nails::installFramework($sPath, 'apache-laravel-php72', 'shedcollective/frontend-laravel');
    }
}
