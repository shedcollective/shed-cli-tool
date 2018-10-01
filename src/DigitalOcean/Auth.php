<?php

namespace App\DigitalOcean;

final class Auth
{
    public static function contexts()
    {
        //  @todo (Pablo - 2018-09-30) - Dynamically list available contexts
        return [
            (object) [
                'slug' => 'shed',
                'name' => 'Shed Collective',
            ],
            (object) [
                'slug' => 'mb',
                'name' => 'Moving Brands',
            ],
        ];
    }

    // --------------------------------------------------------------------------

    public static function contextsAsStrings()
    {
        $aOut = [];
        foreach (static::contexts() as $oContext) {
            $aOut[$oContext->slug] = '<info>' . $oContext->name . '</info> (' . $oContext->slug . ')';
        }
        return $aOut;
    }

    // --------------------------------------------------------------------------

    public static function switchTo($sContext = null)
    {
        return static::call('switch --context="' . $sContext . '"');
    }

    // --------------------------------------------------------------------------

    public static function call($sCommand)
    {
        return Api::call('auth ' . $sCommand);
    }
}
