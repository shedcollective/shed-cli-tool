<?php

namespace App\Helper;

class Debug {

    /**
     * Dumps the value to the screen
     *
     * @param mixed $mValue The value to dump
     */
    public static function dump($mValue)
    {
        echo "\n";
        print_r($mValue);
        echo "\n";
    }

    // --------------------------------------------------------------------------

    /**
     * Dumps the value to the screen, then kills execution
     *
     * @param mixed $mValue The value to dump
     */
    public static function dumpanddie($mValue)
    {
        self::dump($mValue);
        die();
    }
}
