<?php

namespace Shed\Cli\Helper;


use Shed\Cli\Exceptions\Zip\CannotOpenException;

final class Zip
{
    /**
     * Unzip an archive
     *
     * @param string $sZipPath        The path to the archive
     * @param string $sExtractTo      Where to extract the archive to
     * @param string $sInternalFolder The name of any internal folder of the zip
     */
    public static function unzip($sZipPath, $sExtractTo, $sInternalFolder = ''): void
    {
        $oZip = new \ZipArchive();
        if ($oZip->open($sZipPath) === true) {

            $oZip->extractTo($sExtractTo);
            $oZip->close();

            $sInternalFolder = !empty($sInternalFolder) ? rtrim($sInternalFolder, '/') : '';

            $aCommands = [
                'mv ' . $sExtractTo . $sInternalFolder . '/* ' . rtrim($sExtractTo, '/'),
                'mv ' . $sExtractTo . $sInternalFolder . '/.[a-z]* ' . rtrim($sExtractTo, '/'),
                'rm -rf ' . $sZipPath,
                'rm -rf ' . $sExtractTo . $sInternalFolder,
            ];

            System::exec($aCommands);

        } else {
            throw new CannotOpenException('Failed to unzip: ' . $sZipPath);
        }
    }
}
