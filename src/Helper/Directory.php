<?php

namespace App\Helper;

final class Directory
{
    /**
     * Determines whether a directory is empty or not
     *
     * @param string $sPath The path to query
     *
     * @return bool
     */
    public static function isEmpty($sPath)
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
}
