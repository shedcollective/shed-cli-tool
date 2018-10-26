<?php

namespace App\Helper;

final class Updates
{
    /**
     * Checks for updates
     */
    public static function check()
    {
        //  @todo (Pablo - 2018-10-06) - Check for updates if sufficient time has passed (check once daily)
        return false;
    }

    // --------------------------------------------------------------------------

    public static function getCurrentVersion()
    {
        $oComposer = json_decode(file_get_contents(_DIR_ . '../../composer.json'));
        return $oComposer->version;
    }

    // --------------------------------------------------------------------------

    public static function getLatestVersion()
    {

    }
}
