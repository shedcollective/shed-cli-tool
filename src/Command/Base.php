<?php

namespace Shed\Cli\Command;

use Shed\Cli\Helper\Colors;
use Shed\Cli\Helper\Updates;
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

        if (Updates::check()) {
            $this->warning([
                'An update is available: ' . Updates::getLatestVersion() . ' (you have version ' . Updates::getCurrentVersion() . ')',
                'To update run: brew update && brew upgrade nails',
            ]);
        }

        Colors::setStyles($this->oOutput);

        $this->go();
    }

    // --------------------------------------------------------------------------

    /**
     * The command's body
     *
     * @return mixed
     */
    abstract protected function go();

    // --------------------------------------------------------------------------

    /**
     * Display a underlined title banner
     *
     * @param string $sTitle The text to display
     *
     * @return $this
     */
    protected function banner($sTitle)
    {
        $sTitle = $sTitle ? 'Shed CLI: ' . $sTitle : 'Shed CLI';
        $this->oOutput->writeln('');
        $this->oOutput->writeln('<info>' . $sTitle . '</info>');
        $this->oOutput->writeln('<info>' . str_repeat('-', strlen($sTitle)) . '</info>');
        $this->oOutput->writeln('');

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Renders an error block
     *
     * @param array $aLines The lines to render
     */
    protected function error(array $aLines)
    {
        $this->outputBlock($aLines, 'error');
    }

    // --------------------------------------------------------------------------

    /**
     * Renders an warning block
     *
     * @param array $aLines The lines to render
     */
    protected function warning(array $aLines)
    {
        $this->outputBlock($aLines, 'warning');
    }

    // --------------------------------------------------------------------------

    /**
     * Renders an coloured block
     *
     * @param array  $aLines The lines to render
     * @param string $sType  The type of block to render
     */
    protected function outputBlock(array $aLines, $sType)
    {
        $aLengths   = array_map('strlen', $aLines);
        $iMaxLength = max($aLengths);

        $this->oOutput->writeln('<' . $sType . '> ' . str_pad('', $iMaxLength, ' ') . ' </' . $sType . '>');
        foreach ($aLines as $sLine) {
            $this->oOutput->writeln('<' . $sType . '> ' . str_pad($sLine, $iMaxLength, ' ') . ' </' . $sType . '>');
        }
        $this->oOutput->writeln('<' . $sType . '> ' . str_pad('', $iMaxLength, ' ') . ' </' . $sType . '>');
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
        $sQuestion = $this->prepQuestion($sQuestion, $sDefault);
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
     * @return integer
     */
    protected function choose($sQuestion, array $aOptions, $iDefault = 0, $cValidation = null)
    {
        $sQuestion = $this->prepQuestion($sQuestion, $aOptions[$iDefault]);
        $oQuestion = new ChoiceQuestion($sQuestion, $aOptions, $iDefault);
        $sResponse = $this->oQuestion->ask($this->oInput, $this->oOutput, $oQuestion);

        if (is_callable($cValidation) && !call_user_func($cValidation, $sResponse)) {
            return $this->choose($sQuestion, $aOptions, $iDefault, $cValidation);
        }

        return array_search($sResponse, $aOptions);
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
    private function prepQuestion($sQuestion, $sDefault = null)
    {
        $sQuestion = trim($sQuestion);
        if (preg_match('/[^?:]$/', $sQuestion)) {
            $sQuestion .= '?';
        }

        if (!empty($sDefault)) {
            $sQuestion .= ' [' . $sDefault . '] ';
        }

        $sQuestion .= ' ';
        return $sQuestion;
    }
}
