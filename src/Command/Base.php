<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

abstract class Base extends Command
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

    /**
     * The question helper
     *
     * @var QuestionHelper
     */
    protected $oQuestion;

    // --------------------------------------------------------------------------

    /**
     * Execute the command
     *
     * @param InputInterface  $oInput
     * @param OutputInterface $oOutput
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $oInput, OutputInterface $oOutput)
    {
        $this->oInput    = $oInput;
        $this->oOutput   = $oOutput;
        $this->oQuestion = $this->getHelper('question');

        $this->go();
    }

    // --------------------------------------------------------------------------

    abstract protected function go();

    // --------------------------------------------------------------------------

    /**
     * Renders an error block
     *
     * @param array $aLines The lines to render
     */
    protected function error(array $aLines)
    {
        $aLengths   = array_map('strlen', $aLines);
        $iMaxLength = max($aLengths);
        foreach ($aLines as $sLine) {
            $this->oOutput->writeln('<error> ' . str_pad($sLine, $iMaxLength, ' ') . ' </error>');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Ask the user for input
     *
     * @param string   $sQuestion   The question to ask
     * @param string   $sDefault    The default response
     * @param callable $cValidation A validation callback
     *
     * @return mixed
     */
    protected function ask($sQuestion, $sDefault = null, $cValidation = null)
    {
        $sQuestion = $this->prepQuestion($sQuestion);
        $oQuestion = new Question($sQuestion, $sDefault);
        $sResponse = $this->oQuestion->ask($this->oInput, $this->oOutput, $oQuestion);

        if (is_callable($cValidation) && !call_user_func($cValidation, $sResponse)) {
            return $this->ask($sQuestion, $sDefault, $cValidation);
        }

        return $sResponse;
    }

    // --------------------------------------------------------------------------

    /**
     * Ask the user to select an option
     *
     * @param string   $sQuestion   The question to ask
     * @param array    $aOptions    An array of options
     * @param int      $iDefault    The default option
     * @param callable $cValidation A validation callback
     *
     * @return mixed
     */
    protected function choose($sQuestion, array $aOptions, $iDefault = 0, $cValidation = null)
    {
        $sQuestion = $this->prepQuestion($sQuestion);
        $oQuestion = new ChoiceQuestion($sQuestion, $aOptions, $iDefault);
        $sResponse = $this->oQuestion->ask($this->oInput, $this->oOutput, $oQuestion);

        if (is_callable($cValidation) && !call_user_func($cValidation, $sResponse)) {
            return $this->choose($sQuestion, $aOptions, $iDefault, $cValidation);
        }

        return $sResponse;
    }

    // --------------------------------------------------------------------------

    /**
     * Ask the user for confirmation
     *
     * @param string $sQuestion the question to ask
     * @param bool   $bDefault  The default response
     *
     * @return boolean
     */
    protected function confirm($sQuestion, $bDefault = true)
    {
        $sQuestion = $this->prepQuestion($sQuestion);
        $oQuestion = new ConfirmationQuestion($sQuestion, $bDefault);
        return $this->oQuestion->ask($this->oInput, $this->oOutput, $oQuestion);
    }

    // --------------------------------------------------------------------------

    /**
     * Prepare the question string
     *
     * @param string $sQuestion The question to prepare
     *
     * @return string
     */
    private function prepQuestion($sQuestion)
    {
        $sQuestion = trim($sQuestion);
        if (preg_match('/[^?:]$/', $sQuestion)) {
            $sQuestion .= '?';
        }
        $sQuestion .= ' ';
        return $sQuestion;
    }
}
