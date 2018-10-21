<?php

namespace App\DigitalOcean;

use Symfony\Component\Yaml\Yaml;

final class Auth
{
    public static function contexts()
    {
        //  @todo (Pablo - 2018-09-30) - Dynamically list available contexts
        $sPath = $_SERVER['HOME'] . '/.config/doctl/config.yaml';
        if (file_exists($sPath)) {
            $aConfig = Yaml::parseFile($sPath);
        } else {
            $aConfig = [];
        }

        if (empty($aConfig['access-token'])) {
            throw new \Exception('No access token for doctl; please run doctl auth init');
        }

        if (empty($aConfig['auth-contexts'])) {
            return [];
        }

        $aContexts = [];
        foreach ($aConfig['auth-contexts'] as $sSlug => $sToken) {
            $aContexts[] = (object) [
                'slug' => $sSlug,
                'name' => ucwords(str_replace(['-', '_'], ' ', $sSlug)),
            ];
        }

        return $aContexts;
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
