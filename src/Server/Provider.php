<?php

namespace Shed\Cli\Server;

use Shed\Cli\Entity\Provider\Image;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Provider
{
    /**
     * The console's input interface
     *
     * @var InputInterface
     */
    protected $oInput;

    /**
     * The console's output interface
     *
     * @var OutputInterface
     */
    protected $oOutput;

    // --------------------------------------------------------------------------

    /**
     * Command constructor.
     *
     * @param InputInterface  $oInput  The command's input interface
     * @param OutputInterface $oOutput The command's output interface
     */
    public function __construct(InputInterface $oInput, OutputInterface $oOutput)
    {
        $this->oInput  = $oInput;
        $this->oOutput = $oOutput;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the commands to be executed on initial boot as a string
     *
     * @param Image  $oImage     The image to build for
     * @param string $sDeployKey The deploy key, if any, to assign to the deployhq user
     *
     * @return string
     */
    protected static function getStartupScript(Image $oImage, string $sDeployKey): string
    {
        return implode(' && ', static::getStartupCommands($oImage, $sDeployKey));
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the commands to be executed on initial boot as an array
     *
     * @param Image  $oImage     The image to build for
     * @param string $sDeployKey The deploy key, if any, to assign to the deployhq user
     *
     * @return array
     */
    protected static function getStartupCommands(Image $oImage, string $sDeployKey): array
    {
        $aCommands = array_filter([
            'apt-get update',
            'apt-get -y install unzip make',
            'curl -L "https://github.com/shedcollective/startup-scripts/archive/master.zip" > /startup-scripts.zip',
            'unzip /startup-scripts.zip',
            'mv /startup-scripts-master /startup-scripts',
            'rm -rf /startup-scripts-master /startup-scripts.zip',
        ]);

        if ($sDeployKey) {
            $aCommands[] = 'echo "' . $sDeployKey . '" >> /startup-scripts/resources/deployhq.key';
        }

        $aCommands[] = '/startup-scripts/' . $oImage->getslug() . '.sh';

        return $aCommands;
    }
}
