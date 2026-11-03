<?php

namespace Shed\Cli\Helper;

/**
 * Class Trust
 *
 * Whether a binary can be executed as root without handing control to whoever owns the path to it.
 * The heartbeat runs as root, so a binary that anybody else can replace — or that sits in a
 * directory anybody else can write to — is not one it will run.
 *
 * @package Shed\Cli\Helper
 */
final class Trust
{
    /**
     * Both the path as given and the path it resolves to are checked, since a symlink is only as
     * trustworthy as its target
     */
    public static function isRootOwned(string $sPath): bool
    {
        $sReal = realpath($sPath);

        if ($sReal === false) {
            return false;
        }

        return self::isRootOwnedPath($sPath) && ($sReal === $sPath || self::isRootOwnedPath($sReal));
    }

    // --------------------------------------------------------------------------

    /**
     * Root must own every component, and none of them may be group- or other-writable
     */
    private static function isRootOwnedPath(string $sPath): bool
    {
        if (!str_starts_with($sPath, '/')) {
            return false;
        }

        $sCurrent    = '';
        $aComponents = ['/'];

        foreach (explode('/', trim($sPath, '/')) as $sComponent) {
            if ($sComponent === '') {
                continue;
            }

            $sCurrent      = $sCurrent . '/' . $sComponent;
            $aComponents[] = $sCurrent;
        }

        foreach ($aComponents as $sComponent) {
            $iOwner = @fileowner($sComponent);
            $iPerms = @fileperms($sComponent);

            if ($iOwner === false || $iPerms === false || $iOwner !== 0 || ($iPerms & 0022)) {
                return false;
            }
        }

        return true;
    }
}
