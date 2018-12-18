<?php

namespace Shed\Cli\Project\Framework\Frontend;

use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Exceptions\Zip\CannotOpenException;
use Shed\Cli\Helper\System;
use Shed\Cli\Interfaces\Framework;

final class Webpack implements Framework
{
    /**
     * The URL of the Docker skeleton
     *
     * @var string
     */
    const FRONTEND_BOOTSTRAPPER = 'https://github.com/shedcollective/shed-frontend-bootstrapper/archive/master.zip';

    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getName()
    {
        return 'Webpack';
    }

    // --------------------------------------------------------------------------

    /**
     * The configurable options for the framework
     *
     * @return array
     */
    public function getOptions()
    {
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Install the framework
     *
     * @param string $sPath    The absolute directory to install the framework to
     * @param array  $aOptions The result of any options
     *
     * @return void
     * @throws CommandFailedException
     * @throws CannotOpenException
     */
    public function install($sPath, array $aOptions = [])
    {
        //  Download skeleton
        $sZipPath = $sPath . 'webpack.zip';
        file_put_contents($sZipPath, file_get_contents(static::FRONTEND_BOOTSTRAPPER));

        //  Extract
        $oZip = new \ZipArchive();
        if ($oZip->open($sZipPath) === true) {

            $oZip->extractTo($sPath);
            $oZip->close();

            System::exec('mv ' . $sPath . 'shed-frontend-bootstrapper-master/* ' . rtrim($sPath, '/') . '/www');
            System::exec('mv ' . $sPath . 'shed-frontend-bootstrapper-master/.[a-z]* ' . rtrim($sPath, '/') . '/www');

        } else {
            throw new CannotOpenException('Failed to unzip: ' . $sZipPath);
        }
    }
}
