<?php

namespace App\Project\Create\Backend;

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
     */
    public function install($sPath)
    {
        //  @todo (Pablo - 2018-10-21) - Install WordPress
    }
}
