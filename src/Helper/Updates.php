<?php

namespace Shed\Cli\Helper;

final class Updates
{
    private static $sCurrentVersion;
    private static $sLatestVersion;

    // --------------------------------------------------------------------------

    /**
     * Checks for updates
     *
     * @return bool
     */
    public static function check(): bool
    {
        //  @todo (Pablo - 2018-10-06) - Check for updates if sufficient time has passed (check once daily)
        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the current version of the application
     *
     * @return string
     */
    public static function getCurrentVersion(): string
    {
        if (static::$sCurrentVersion) {
            return static::$sCurrentVersion;
        } else {

            $oComposer = json_decode(
                file_get_contents(
                    Directory::normalize(__DIR__ . '/../../composer.json')
                )
            );

            static::$sCurrentVersion = $oComposer->version;
            return static::$sCurrentVersion;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Get the latest version of the app from GitHub
     *
     * @return string
     */
    public static function getLatestVersion(): string
    {
        if (static::$sLatestVersion) {
            return static::$sLatestVersion;
        } else {
            //  @todo (Pablo - 2018-12-13) - Query something for the latest version
            static::$sLatestVersion = 'x.x.x';
            return static::$sLatestVersion;
        }
    }
}
