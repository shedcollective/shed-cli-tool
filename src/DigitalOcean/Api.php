<?php

namespace App\DigitalOcean;

final class Api
{
    public static function isAvailable()
    {
        //  @todo (Pablo - 2018-09-30) - Detect if `doctl` is installed
        //  @todo (Pablo - 2018-09-30) - Test if `exec()` is enabled
    }

    // --------------------------------------------------------------------------

    public static function call($sCommand)
    {
        $sCommand = 'doctl ' . $sCommand;
        exec($sCommand, $aOutput, $iReturnValue);

        if ($iReturnValue) {
            throw new \Exception('Command "' . $sCommand . '" returned a non-zero exit code');
        }

        return $aOutput;
    }
}
