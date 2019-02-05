<?php

namespace Shed\Cli\Command\Server;

use Shed\Cli\Command\Base;
use Shed\Cli\Exceptions\Environment\NotValidException;
use Shed\Cli\Helper\Debug;
use Shed\Cli\Helper\System;
use Shed\Cli\Interfaces\Infrastructure;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;

final class Create extends Base
{
    /**
     * The domain name
     *
     * @var string
     */
    private $sDomain = null;

    /**
     * The infrastructure to use
     *
     * @var Infrastructure
     */
    private $oInfrastructure = null;

    /**
     * The infrastructure options
     *
     * @var array
     */
    private $aInfrastructureOptions = [];

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('server:create')
            ->setDescription('Create a new server')
            ->setHelp('This command will interactively create and configure a new server.')
            ->addOption(
                'infrastructure',
                'i',
                InputOption::VALUE_OPTIONAL,
                'The infrastructure to use'
            )
            ->addOption(
                'domain',
                'd',
                InputOption::VALUE_OPTIONAL,
                'The domain name'
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Execute the command
     *
     * @return int
     */
    protected function go(): int
    {
        $this
            ->banner('Setting up a new server')
            ->checkEnvironment()
            ->setVariables();

        if ($this->confirmVariables()) {
            $this->createServer();
        }

        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    /**
     * Validates that the environment is usable
     *
     * @throws NotValidException
     *
     * @return $this
     */
    private function checkEnvironment(): Create
    {
        if (!function_exists('exec')) {
            throw new NotValidException('Missing function exec()');
        }

        $aRequiredCommands = ['composer'];
        foreach ($aRequiredCommands as $sRequiredCommand) {
            if (!System::commandExists($sRequiredCommand)) {
                throw new NotValidException($sRequiredCommand . ' is not installed');
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Configures the create command
     *
     * @return $this
     */
    private function setVariables(): Create
    {
        return $this
            ->setDomain()
            ->setInfrastructure(
                $this->oInfrastructure,
                $this->oInput->getOption('infrastructure')
            )
            ->setInfrastructureOptions(
                $this->oInfrastructure,
                $this->aInfrastructureOptions
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the domain property
     *
     * @return $this
     */
    private function setDomain(): Create
    {
        //  @todo (Pablo - 2019-02-04) - Do not allow empty value
        $sOption = $this->oInput->getOption('domain');
        if (empty($sOption)) {
            $this->sDomain = $this->ask(
                'Domain Name:',
                null,
                [$this, 'validateDomain']
            );
        } else {
            if ($this->validateDomain($sOption)) {
                $this->sDomain = $sOption;
            } else {
                $this->sDomain = $this->ask(
                    'Domain Name:',
                    null,
                    [$this, 'validateDomain']
                );
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Validate a domain is valid
     *
     * @param string $sDomain The domain to test
     *
     * @return bool
     */
    protected function validateDomain($sDomain): bool
    {
        $sTestDomain = $sDomain;
        if (!preg_match('/^http/', $sTestDomain)) {
            $sTestDomain = 'https://' . $sTestDomain;
        }

        $aDomain = parse_url($sTestDomain);

        if (empty($aDomain) || !array_key_exists('host', $aDomain)) {
            $this->error(array_filter([
                'Invalid domain',
                $sDomain,
            ]));
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Looks up and sets the backend framework
     *
     * @param Infrastructure $oProperty The property to assign the framework to
     * @param string         $sOption   The value of the framework CLI option
     *
     * @return $this
     */
    private function setInfrastructure(&$oProperty, $sOption = null): Create
    {
        $aInfrastructures           = [];
        $aInfrastructuresNormalised = [];
        $aInfrastructureClasses     = [];

        $sBasePath = BASEPATH . 'src';
        $oFinder   = new Finder();
        $oFinder->files()->in($sBasePath . '/Server/Infrastructure/');
        foreach ($oFinder as $oFile) {

            $sClassName = $oFile->getBasename('.php');
            if ($sClassName === 'Base') {
                continue;
            }

            $sInfrastructure = $oFile->getPath() . '/' . $sClassName;
            $sInfrastructure = str_replace($sBasePath, 'Shed\Cli', $sInfrastructure);
            $sInfrastructure = str_replace('/', '\\', $sInfrastructure);

            $oInfrastructure = new $sInfrastructure(
                $this->oInput,
                $this->oOutput
            );

            $sInfrastructureName          = $oInfrastructure->getName();
            $aInfrastructures[]           = $sInfrastructureName;
            $aInfrastructuresNormalised[] = strtoupper($sInfrastructureName);
            $aInfrastructureClasses[]     = $oInfrastructure;
        }

        if (count($aInfrastructures) === 0) {
            throw new \RuntimeException('No infrastructures available');
        } elseif (!empty($sOption)) {

            $iChoice = array_search(strtoupper($sOption), $aInfrastructuresNormalised);
            if ($iChoice === false) {
                $this->error([
                    '"' . $sOption . '" is not a valid infrastructure option',
                ]);
                return $this->setInfrastructure($oProperty);
            }

        } elseif (count($aInfrastructures) === 1) {
            $this->oOutput->writeln('Only one infrastructure available: ' . $aInfrastructures[0]);
            $iChoice = 0;
        } else {
            $iChoice = $this->choose('Infrastructure', $aInfrastructures);
        }

        $oProperty = $aInfrastructureClasses[$iChoice];

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Configures infrastructure options
     *
     * @param Infrastructure $oInfrastructure The infrastructure to configure
     * @param array          $aOptions        The property to assign the results to
     *
     * @return $this
     */
    private function setInfrastructureOptions($oInfrastructure, &$aOptions): Create
    {
        foreach ($oInfrastructure->getOptions() as $sKey => $oOption) {

            $sType       = property_exists($oOption, 'type') ? $oOption->type : 'ask';
            $mChoices    = property_exists($oOption, 'options') ? $oOption->options : null;
            $mDefault    = property_exists($oOption, 'default') ? $oOption->default : null;
            $mValidation = property_exists($oOption, 'validation') ? $oOption->validation : null;

            if ($sType === 'ask') {

                $aOptions[$sKey] = $this->ask(
                    $oOption->label,
                    $mDefault,
                    $mValidation
                );

            } elseif ($sType === 'choose' && !empty($mChoices)) {

                if (is_callable($mChoices)) {
                    $aChoices = call_user_func($mChoices);
                } else {
                    $aChoices = $mChoices;
                }

                $aOptions[$sKey] = $this->choose(
                    $oOption->label,
                    $aChoices,
                    $mDefault,
                    $mValidation
                );
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Confirms the selected options
     *
     * @return bool
     */
    private function confirmVariables()
    {
        $this->oOutput->writeln('');
        $this->oOutput->writeln('Does this all look OK?');
        $this->oOutput->writeln('');
        $this->oOutput->writeln('<comment>Domain</comment>:         ' . $this->sDomain);
        $this->oOutput->writeln('<comment>Infrastructure</comment>: ' . $this->oInfrastructure->getName());
        //  @todo (Pablo - 2019-02-05) - List infrastructure variables
        $this->oOutput->writeln('');
        return $this->confirm('Continue?');
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new project
     *
     * @return $this
     */
    private function createServer(): Create
    {
        $this->oOutput->writeln('');

        //  @todo (Pablo - 2019-02-04) - Create the server

        $this->oOutput->writeln('');
        $this->oOutput->writeln('🚧 command is a work in progress');
        //  @todo (Pablo - 2019-02-04) - More useful messages
        $this->oOutput->writeln('');
        return $this;
    }
}
