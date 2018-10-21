<?php

namespace App\Project\Create\Frontend;

use App\Interfaces\Framework;

final class React implements Framework
{
    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getName()
    {
        return 'React';
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
        //  @todo (Pablo - 2018-10-21) - Install React
    }
}
