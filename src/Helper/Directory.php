<?php

namespace Shed\Cli\Helper;

final class Directory
{
    /**
     * Normalizes *nix style forward slashes with the system's DIRECTORY_SEPARATOR
     *
     * @param string $sPath The path to normalise
     *
     * @return string
     */
    public static function normalize(string $sPath): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $sPath);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns whether a directory exists or not
     *
     * @param string $sPath The directory to test
     *
     * @return bool
     */
    public static function exists(string $sPath): bool
    {
        return is_dir($sPath);
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a directory is empty or not
     *
     * @param string $sPath The path to query
     *
     * @return bool
     */
    public static function isEmpty(string $sPath): bool
    {
        if (!is_dir($sPath)) {
            return true;
        }

        $hDir = opendir($sPath);
        while (false !== ($sEntry = readdir($hDir))) {
            if ($sEntry != '.' && $sEntry != '..') {
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Resolves a path to an absolute path
     *
     * @param string $sPath the path to resolve
     *
     * @return string
     */
    public static function resolve(string $sPath): string
    {
        $sPath = trim($sPath);

        //  Resolve ~/
        if (array_key_exists('HOME', $_SERVER)) {
            $sPath = preg_replace('/^~\//', $_SERVER['HOME'] . '/', $sPath);
        }

        //  Resolve ./
        $sPath = preg_replace('/^\.\//', getcwd() . DIRECTORY_SEPARATOR, $sPath);

        //  Resolve relative URLs
        if (!preg_match('/^\//', $sPath)) {
            $sPath = getcwd() . DIRECTORY_SEPARATOR . $sPath;
        }

        if (!preg_match('/\/$/', $sPath)) {
            $sPath .= '/';
        }

        return $sPath;
    }

    // --------------------------------------------------------------------------

    /**
     * Resolve a file path
     *
     * @param string $sPath The path to resolve
     *
     * @return string
     */
    public static function resolvePath(string $sPath): string
    {
        $sDir  = static::resolve(dirname($sPath));
        $sFile = basename($sPath);
        return $sDir . $sFile;
    }
}
