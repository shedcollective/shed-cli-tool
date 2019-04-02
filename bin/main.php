#!/usr/bin/env php
<?php

namespace Shed\Cli;

use Shed\Cli\Helper\Config;
use Shed\Cli\Helper\Directory;
use Shed\Cli\Helper\Updates;
use Symfony\Component\Console\Application;
use Symfony\Component\Finder\Finder;

// --------------------------------------------------------------------------

require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
define('BASEPATH', Directory::normalize(__DIR__ . '/../'));

// --------------------------------------------------------------------------

define('APP_NAME', 'Shed Command Line Tool');

// --------------------------------------------------------------------------

$oApp    = new Application();
$oFinder = new Finder();

Config::loadConfig();

//  Auto-load commands
$sBasePath = BASEPATH . 'src';
$oFinder->files()->in($sBasePath . '/Command');

foreach ($oFinder as $oFile) {

    $sCommand = $oFile->getPath() . DIRECTORY_SEPARATOR . $oFile->getBasename('.php');
    $sCommand = str_replace($sBasePath, 'Shed/Cli', $sCommand);
    $sCommand = str_replace(DIRECTORY_SEPARATOR, '\\', $sCommand);

    $oReflection = new \ReflectionClass($sCommand);
    if (!$oReflection->isAbstract()) {
        $oApp->add(new $sCommand());
    }
}

$oApp->setName(APP_NAME);
$oApp->setVersion(Updates::getCurrentVersion());
$oApp->run();
