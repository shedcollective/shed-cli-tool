<?php

namespace App\DigitalOcean\Compute;

use App\DigitalOcean\Compute;

final class Droplet
{
    public static function create($sVariables)
    {
        return static::call('create ' . $sVariables);
    }

    // --------------------------------------------------------------------------

    public static function call($sCommand)
    {
        return Compute::call('droplet ' . $sCommand);
    }
}
