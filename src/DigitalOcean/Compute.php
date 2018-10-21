<?php

namespace App\DigitalOcean;

final class Compute
{
    public static function sshKeys()
    {
        $aKeys              = static::call('ssh-key list');
        $sColumns           = array_shift($aKeys);
        $iColumnId          = strpos($sColumns, 'ID');
        $iColumnName        = strpos($sColumns, 'Name');
        $iColumnFingerprint = strpos($sColumns, 'FingerPrint');

        $aKeys = array_map(
            function ($sKey) use ($iColumnId, $iColumnName, $iColumnFingerprint) {
                return (object) [
                    'id'          => trim(substr($sKey, $iColumnId, ($iColumnName - $iColumnId))),
                    'name'        => trim(substr($sKey, $iColumnName, ($iColumnFingerprint - $iColumnName))),
                    'fingerprint' => trim(substr($sKey, $iColumnFingerprint)),
                ];
            },
            $aKeys
        );

        return $aKeys;
    }

    // --------------------------------------------------------------------------

    public static function sshKeysAsStrings()
    {
        $aOut = [];
        foreach (static::sshKeys() as $oKey) {
            $aOut[$oKey->id] = '<info>' . $oKey->name . '</info> (' . $oKey->id . ')';
        }
        return $aOut;
    }

    // --------------------------------------------------------------------------

    public static function regions($bOnlyAvailable = false)
    {
        $aRegions         = static::call('region list');
        $sColumns         = array_shift($aRegions);
        $iColumnSlug      = strpos($sColumns, 'Slug');
        $iColumnName      = strpos($sColumns, 'Name');
        $iColumnAvailable = strpos($sColumns, 'Available');

        $aRegions = array_map(
            function ($sRegion) use ($iColumnSlug, $iColumnName, $iColumnAvailable) {
                return (object) [
                    'slug'      => trim(substr($sRegion, $iColumnSlug, ($iColumnName - $iColumnSlug))),
                    'name'      => trim(substr($sRegion, $iColumnName, ($iColumnAvailable - $iColumnName))),
                    'available' => trim(substr($sRegion, $iColumnAvailable)) === 'true',
                ];
            },
            $aRegions
        );

        if ($bOnlyAvailable) {
            $aRegions = array_values(
                array_filter(
                    $aRegions,
                    function ($oRegion) {
                        return $oRegion->available;
                    }
                )
            );
        }

        return $aRegions;
    }

    // --------------------------------------------------------------------------

    public static function regionsAsStrings($bOnlyAvailable = false)
    {
        $aOut = [];
        foreach (static::regions($bOnlyAvailable) as $oRegion) {
            $aOut[$oRegion->slug] = '<info>' . $oRegion->name . '</info> (' . $oRegion->slug . ')';
        }
        return $aOut;
    }

    // --------------------------------------------------------------------------

    public static function sizes()
    {
        $aSizes              = static::call('size list');
        $sColumns            = array_shift($aSizes);
        $iColumnSlug         = strpos($sColumns, 'Slug');
        $iColumnMemory       = strpos($sColumns, 'Memory');
        $iColumnVcpus        = strpos($sColumns, 'VCPUs');
        $iColumnDisk         = strpos($sColumns, 'Disk');
        $iColumnPriceMonthly = strpos($sColumns, 'Price Monthly');
        $iColumnPriceHourly  = strpos($sColumns, 'Price Hourly');

        $aSizes = array_map(
            function ($sSize) use (
                $iColumnSlug,
                $iColumnMemory,
                $iColumnVcpus,
                $iColumnDisk,
                $iColumnPriceMonthly,
                $iColumnPriceHourly
            ) {
                return (object) [
                    'slug'   => trim(substr($sSize, $iColumnSlug, ($iColumnMemory - $iColumnSlug))),
                    'memory' => trim(substr($sSize, $iColumnMemory, ($iColumnVcpus - $iColumnMemory))),
                    'vcpus'  => trim(substr($sSize, $iColumnVcpus, ($iColumnDisk - $iColumnVcpus))),
                    'disk'   => trim(substr($sSize, $iColumnDisk, ($iColumnPriceMonthly - $iColumnDisk))),
                    'price'  => trim(substr($sSize, $iColumnPriceMonthly, ($iColumnPriceHourly - $iColumnPriceMonthly))),
                ];
            },
            $aSizes
        );

        return $aSizes;
    }

    // --------------------------------------------------------------------------

    public static function sizesAsStrings()
    {
        $aOut = [];
        foreach (static::sizes() as $oSize) {
            $aOut[$oSize->slug] = '<info>' . $oSize->memory . ' Mb Memory / ' . $oSize->vcpus . ' VCPUs / ' . $oSize->disk . ' Gb Disk / $' . $oSize->price . ' Per month</info> (' . $oSize->slug . ')';
        }
        return $aOut;
    }

    // --------------------------------------------------------------------------

    public static function images()
    {
        //  @todo (Pablo - 2018-10-06) - Filter by custom images only
        $aImages             = static::call('image list-application --public');
        $sColumns            = array_shift($aImages);
        $iColumnId           = strpos($sColumns, 'ID');
        $iColumnName         = strpos($sColumns, 'Name');
        $iColumnType         = strpos($sColumns, 'Type');
        $iColumnDistribution = strpos($sColumns, 'Distribution');
        $iColumnSlug         = strpos($sColumns, 'Slug');
        $iColumnPublic       = strpos($sColumns, 'Public');
        $iColumnMinDisk      = strpos($sColumns, 'Min Disk');

        $aImages = array_map(
            function ($sImage) use (
                $iColumnId,
                $iColumnName,
                $iColumnType,
                $iColumnDistribution,
                $iColumnSlug,
                $iColumnPublic,
                $iColumnMinDisk
            ) {
                return (object) [
                    'id'           => trim(substr($sImage, $iColumnId, ($iColumnName - $iColumnId))),
                    'name'         => trim(substr($sImage, $iColumnName, ($iColumnType - $iColumnName))),
                    'type'         => trim(substr($sImage, $iColumnType, ($iColumnDistribution - $iColumnType))),
                    'distribution' => trim(substr($sImage, $iColumnDistribution, ($iColumnSlug - $iColumnDistribution))),
                    'slug'         => trim(substr($sImage, $iColumnSlug, ($iColumnPublic - $iColumnSlug))),
                    'public'       => trim(substr($sImage, $iColumnPublic, ($iColumnMinDisk - $iColumnPublic))) === 'true',
                    'mindisk'      => trim(substr($sImage, $iColumnMinDisk)),
                ];
            },
            $aImages
        );

        return $aImages;
    }

    // --------------------------------------------------------------------------

    public static function imagesAsStrings()
    {
        $aOut = [];
        foreach (static::images() as $oImage) {
            $aOut[$oImage->slug] = '<info>' . $oImage->name . '</info> (' . $oImage->slug . ')';
        }
        return $aOut;
    }

    // --------------------------------------------------------------------------

    public static function call($sCommand)
    {
        return Api::call('compute ' . $sCommand);
    }
}
