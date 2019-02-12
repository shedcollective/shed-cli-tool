<?php

namespace Shed\Cli\Server\Provider;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Base
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
     * Base constructor.
     *
     * @param InputInterface  $oInput  The command's input interface
     * @param OutputInterface $oOutput The command's output interface
     */
    public function __construct(InputInterface $oInput, OutputInterface $oOutput)
    {
        $this->oInput  = $oInput;
        $this->oOutput = $oOutput;
    }
}
