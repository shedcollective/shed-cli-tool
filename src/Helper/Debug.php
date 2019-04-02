<?php

namespace Shed\Cli\Helper;

final class Debug
{
    /**
     * Dumps the value to the screen
     *
     * @param mixed $mValue The value to dump
     */
    public static function d($mValue): void
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
    public static function dd($mValue): void
    {
        self::d($mValue);
        die();
    }
}
