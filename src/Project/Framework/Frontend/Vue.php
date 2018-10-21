<?php

namespace App\Project\Framework\Frontend;

use App\Interfaces\Framework;

final class Vue implements Framework
{
    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getName()
    {
        return 'Vue';
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
        //  @todo (Pablo - 2018-10-21) - Install Vue
    }
}
