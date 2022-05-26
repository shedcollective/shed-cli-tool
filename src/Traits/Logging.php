<?php

namespace Shed\Cli\Traits;

use Symfony\Component\Console\Output\OutputInterface;

trait Logging
{
    /**
     * The console's output interface
     *
     * @var OutputInterface
     */
    protected $oOutput;

    protected function log($messages): self
    {
        $this->oOutput->write($messages);
        return $this;
    }

    protected function logln($messages): self
    {
        $this->oOutput->writeln($messages);
        return $this;
    }

    protected function logVerbose($messages): self
    {
        $this->oOutput->write($messages, false, $this->oOutput::VERBOSITY_VERBOSE);
        return $this;
    }

    protected function loglnVerbose($messages): self
    {
        $this->oOutput->writeln($messages, $this->oOutput::VERBOSITY_VERBOSE);
        return $this;
    }

    protected function logVeryVerbose($messages): self
    {
        $this->oOutput->write($messages, false, $this->oOutput::VERBOSITY_VERY_VERBOSE);
        return $this;
    }

    protected function loglnVeryVerbose($messages): self
    {
        $this->oOutput->writeln($messages, $this->oOutput::VERBOSITY_VERY_VERBOSE);
        return $this;
    }
}
