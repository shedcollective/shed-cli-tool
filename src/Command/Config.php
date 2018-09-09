<?php

namespace App\Command;

use App\Helper\Output;

final class Config extends Base {

    /**
     * Describes what the command does
     */
    const INFO = 'Shows the computed configuration options';

    // --------------------------------------------------------------------------

    /**
     * Called when the command is executed
     */
    public function execute()
    {
        $aConfig = $this->oApp->config();
        $sConfig = json_encode($aConfig, JSON_PRETTY_PRINT);

        //  A bit of colouring
        //  Strings
        $sConfig = preg_replace('/"(.*?)": "(.*?)"/', '"<comment>$1</comment>": "<comment>$2</comment>"', $sConfig);
        $sConfig = preg_replace('/"([a-zA-Z_].*?)"/', '"<comment>$1</comment>"', $sConfig);

        //  Integers
        $sConfig = preg_replace('/(: )([0-9]+)/', '$1<info>$2</info>', $sConfig);

        //  Booleans
        $sConfig = preg_replace('/(: )(true|false)/', '$1<info>$2</info>', $sConfig);

        Output::line($sConfig);
    }
}
