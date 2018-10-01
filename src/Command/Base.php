<?php

namespace App\Command;

use App\Helper\Output;

abstract class Base
{

    /**
     * Describes what the command does
     */
    const INFO = '<error>Undefined</error>';

    // --------------------------------------------------------------------------

    /**
     * A reference to the parent app instance
     *
     * @var \App\App
     */
    protected $oApp;

    // --------------------------------------------------------------------------

    /**
     * Base constructor.
     *
     * @param $oApp
     */
    public function __construct($oApp)
    {
        $this->oApp = $oApp;
    }

    // --------------------------------------------------------------------------

    /**
     * Called when the command is executed
     */
    public function execute()
    {
    }
}
