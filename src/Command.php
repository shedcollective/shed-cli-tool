<?php

namespace Shed\Cli;

use Shed\Cli\Helper\Colors;
use Shed\Cli\Helper\Updates;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

abstract class Command extends \Symfony\Component\Console\Command\Command
{
    /**
     * The successful exit code
     *
     * @var int
     */
    const EXIT_CODE_SUCCESS = 0;

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
     * Execute the command
     *
     * @param InputInterface  $oInput
     * @param OutputInterface $oOutput
     *
     * @return int
     */
    protected function execute(InputInterface $oInput, OutputInterface $oOutput): int
    {
        $this->oInput  = $oInput;
        $this->oOutput = $oOutput;

        Colors::setStyles($this->oOutput);

        if (Updates::check()) {

            $aLines = [
                'An update is available: ' . Updates::getLatestVersion() . ' (you have version ' . Updates::getCurrentVersion() . ')',
            ];

            exec('brew list shedcollective/utilities/shed 2>&1', $aOutput, $iReturnVal);
            $bInstalledViaBrew     = $iReturnVal === 0;
            $bInstalledViaComposer = false;

            if (!$bInstalledViaBrew) {
                exec('composer global info shedcollective/command-line-tool 2>&1', $aOutput, $iReturnVal);
                $bInstalledViaComposer = $iReturnVal === 0;
            }

            if ($bInstalledViaBrew) {
                $aLines[] = 'To update run: brew update && brew upgrade shed';
            } elseif ($bInstalledViaComposer) {
                $aLines[] = 'To update run: composer global update shed/command-line-tool';
            }

            $this->oOutput->writeln('');
            $this->warning($aLines);
        }

        return $this->go();
    }

    // --------------------------------------------------------------------------

    /**
     * The command's body
     *
     * @return int
     */
    abstract protected function go(): int;

    // --------------------------------------------------------------------------

    /**
     * Display a underlined title banner
     *
     * @param string $sTitle The text to display
     *
     * @return $this
     */
    protected function banner(string $sTitle): Command
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
     * Display a neatly aligned key-value listing
     *
     * @param array $aKeyValuePairs
     *
     * @return $this
     */
    protected function keyValueList(array $aKeyValuePairs, string $sHeader = ''): Command
    {
        $aKeys       = array_keys($aKeyValuePairs);
        $aKeyLengths = array_map('strlen', $aKeys);
        $iMaxLength  = max($aKeyLengths);

        $this->oOutput->writeln('');
        if ($sHeader) {
            $this->oOutput->writeln($sHeader);
            $this->oOutput->writeln(str_pad('', strlen($sHeader), '-'));
        }
        foreach ($aKeyValuePairs as $sKey => $sValue) {
            $this->oOutput->writeln(
                sprintf(
                    '<comment>%s</comment>: %s%s',
                    $sKey,
                    str_pad('', $iMaxLength - strlen($sKey), ' '),
                    $sValue
                )
            );
        }
        $this->oOutput->writeln('');

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Renders an error block
     *
     * @param array $aLines The lines to render
     */
    protected function error(array $aLines): void
    {
        $this->outputBlock($aLines, 'error');
    }

    // --------------------------------------------------------------------------

    /**
     * Renders an warning block
     *
     * @param array $aLines The lines to render
     */
    protected function warning(array $aLines): void
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
    protected function outputBlock(array $aLines, $sType): void
    {
        $aLines   = array_map(function ($sLine) {
            return ' ' . $sLine . ' ';
        }, $aLines);
        $aLengths = array_map('strlen', $aLines);

        if (!empty($aLengths)) {

            $iMaxLength = max($aLengths);

            $this->oOutput->writeln('<' . $sType . '> ' . str_pad('', $iMaxLength, ' ') . ' </' . $sType . '>');
            foreach ($aLines as $sLine) {
                $this->oOutput->writeln('<' . $sType . '> ' . str_pad($sLine, $iMaxLength, ' ') . ' </' . $sType . '>');
            }
            $this->oOutput->writeln('<' . $sType . '> ' . str_pad('', $iMaxLength, ' ') . ' </' . $sType . '>');
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
     * @return string|null
     */
    protected function ask($sQuestion, $sDefault = null, $cValidation = null): ?string
    {
        $oHelper   = $this->getHelper('question');
        $sQuestion = $this->prepQuestion($sQuestion, $sDefault);
        $oQuestion = new Question($sQuestion, $sDefault);
        $sResponse = $oHelper->ask($this->oInput, $this->oOutput, $oQuestion);

        if (is_callable($cValidation) && !call_user_func($cValidation, $sResponse)) {
            return $this->ask($sQuestion, $sDefault, $cValidation);
        }

        return trim($sResponse);
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
     * @return int
     */
    protected function choose($sQuestion, array $aOptions, $iDefault = 0, $cValidation = null): int
    {
        $oHelper   = $this->getHelper('question');
        $mDefault  = array_key_exists($iDefault, $aOptions) ? $aOptions[$iDefault] : null;
        $sQuestion = $this->prepQuestion($sQuestion, $mDefault);
        $oQuestion = new ChoiceQuestion($sQuestion, $aOptions, $iDefault);
        $sResponse = $oHelper->ask($this->oInput, $this->oOutput, $oQuestion);

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
     * @return bool
     */
    protected function confirm($sQuestion, $bDefault = true): bool
    {
        $oHelper   = $this->getHelper('question');
        $sQuestion = $this->prepQuestion($sQuestion);
        $oQuestion = new ConfirmationQuestion($sQuestion, $bDefault);
        return $oHelper->ask($this->oInput, $this->oOutput, $oQuestion);
    }

    // --------------------------------------------------------------------------

    /**
     * Prepare the question string
     *
     * @param string $sQuestion The question to prepare
     * @param string $sDefault  The default value
     *
     * @return string
     */
    private function prepQuestion($sQuestion, $sDefault = null): string
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
