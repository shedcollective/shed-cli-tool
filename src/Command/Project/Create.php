<?php

namespace Shed\Cli\Command\Project;

use Shed\Cli\Command\Base;
use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Exceptions\Directory\FailedToCreateException;
use Shed\Cli\Exceptions\Environment\NotValidException;
use Shed\Cli\Exceptions\Zip\CannotOpenException;
use Shed\Cli\Helper\Directory;
use Shed\Cli\Helper\System;
use Shed\Cli\Helper\Zip;
use Shed\Cli\Interfaces\Framework;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

final class Create extends Base
{
    /**
     * The URL of the Docker skeleton
     *
     * @var string
     */
    const DOCKER_SKELETON = 'https://github.com/nails/skeleton-docker-lamp/archive/master.zip';

    /**
     * The project name
     *
     * @var string
     */
    private $sProjectName = null;

    /**
     * The project slug
     *
     * @var string
     */
    private $sProjectSlug = null;

    /**
     * Where to create the project
     *
     * @var string
     */
    private $sDir = null;

    /**
     * The backend framework to use
     *
     * @var Framework
     */
    private $oBackendFramework = null;

    /**
     * The backend framework options
     *
     * @var array
     */
    private $oBackendFrameworkOptions = [];

    /**
     * The frontend framework to use
     *
     * @var Framework
     */
    private $oFrontendFramework = null;

    /**
     * The frontend framework options
     *
     * @var array
     */
    private $oFrontendFrameworkOptions = [];

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('project:create')
            ->setDescription('Create a new project')
            ->setHelp('This command will interactively create and configure a new project.')
            ->addOption(
                'name',
                'p',
                InputOption::VALUE_OPTIONAL,
                'The name of the project'
            )
            ->addOption(
                'slug',
                's',
                InputOption::VALUE_OPTIONAL,
                'The slug of the project'
            )
            ->addOption(
                'directory',
                'd',
                InputOption::VALUE_OPTIONAL,
                'The directory to create the project in, if empty then the current working directory is used'
            )
            ->addOption(
                'backend-framework',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Which backend framework to use'
            )
            ->addOption(
                'frontend-framework',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Which frontend framework to use'
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Execute the command
     *
     * @return int|null|void
     * @throws CannotOpenException
     * @throws CommandFailedException
     * @throws NotValidException
     * @throws FailedToCreateException
     */
    protected function go()
    {
        $this
            ->banner('Setting up a new project')
            ->checkEnvironment()
            ->setVariables();

        if ($this->confirmVariables()) {
            $this->createProject();
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Validates that the environment is usable
     *
     * @throws NotValidException
     *
     * @return $this
     */
    private function checkEnvironment()
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
    private function setVariables()
    {
        return $this
            ->setProjectName()
            ->setProjectSlug()
            ->setDirectory()
            ->setFramework(
                'Backend',
                $this->oBackendFramework,
                $this->oInput->getOption('backend-framework')
            )
            ->setFrameworkOptions(
                $this->oBackendFramework,
                $this->oBackendFrameworkOptions
            )
            ->setFramework(
                'Frontend',
                $this->oFrontendFramework,
                $this->oInput->getOption('frontend-framework')
            )
            ->setFrameworkOptions(
                $this->oFrontendFramework,
                $this->oFrontendFrameworkOptions);
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the project name property
     *
     * @return $this
     */
    private function setProjectName()
    {
        $sOption = $this->oInput->getOption('name');
        if (empty($sOption)) {
            $this->sProjectName = $this->ask('Project Name:');
        } else {
            $this->sProjectName = $sOption;
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the project slug property
     *
     * @return $this
     */
    private function setProjectSlug()
    {
        if ($this->oInput->getOption('slug')) {
            $this->sProjectSlug = $this->oInput->getOption('slug');
        } else {
            $sDefault           = strtolower($this->sProjectName);
            $sDefault           = preg_replace('/[^a-z0-9\- ]/', '', $sDefault);
            $sDefault           = str_replace(' ', '-', $sDefault);
            $this->sProjectSlug = $this->ask('Project Slug:', $sDefault);
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Set where to create the project
     *
     * @return $this
     */
    private function setDirectory()
    {
        $sOption = $this->oInput->getOption('directory');
        if (empty($sOption)) {
            $this->sDir = $this->ask(
                'Project Directory <info>(Leave blank for current directory)</info>:',
                null,
                [$this, 'validateDirectory']
            );
        } else {
            if ($this->validateDirectory($sOption)) {
                $this->sDir = $sOption;
            } else {
                $this->sDir = $this->ask(
                    'Project Directory <info>(Leave blank for current directory)</info>:',
                    null,
                    [$this, 'validateDirectory']
                );
            }
        }

        $this->sDir = Directory::resolve($this->sDir);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Validate that a directory is empty
     *
     * @param string $sDirectory The directory to test
     *
     * @return bool
     */
    protected function validateDirectory($sDirectory)
    {
        $sDirectory = Directory::resolve($sDirectory);
        if (!Directory::isEmpty($sDirectory)) {
            $this->error([
                'Directory is not empty',
                $sDirectory,
            ]);
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Looks up and sets the backend framework
     *
     * @param string    $sNamespace The namespace of frameworks to use
     * @param Framework $oProperty  The property to assign the framework to
     * @param string    $sOption    The value of the framework CLI option
     *
     * @return $this
     */
    private function setFramework($sNamespace, &$oProperty, $sOption = null)
    {
        $aFrameworks           = [];
        $aFrameworksNormalised = [];
        $aFrameworkClasses     = [];

        $sBasePath = BASEPATH . 'src';
        $oFinder   = new Finder();
        $oFinder->files()->in($sBasePath . '/Project/Framework/' . $sNamespace . '/');
        foreach ($oFinder as $oFile) {

            $sFramework = $oFile->getPath() . '/' . $oFile->getBasename('.php');
            $sFramework = str_replace($sBasePath, 'Shed\Cli', $sFramework);
            $sFramework = str_replace('/', '\\', $sFramework);

            $oFramework              = new $sFramework();
            $sFrameworkName          = $oFramework->getName();
            $aFrameworks[]           = $sFrameworkName;
            $aFrameworksNormalised[] = strtoupper($sFrameworkName);
            $aFrameworkClasses[]     = $oFramework;
        }

        if (count($aFrameworks) === 0) {
            throw new \RuntimeException('No ' . $sNamespace . ' frameworks available');
        } elseif (!empty($sOption)) {

            $iChoice = array_search(strtoupper($sOption), $aFrameworksNormalised);
            if ($iChoice === false) {
                $this->error([
                    '"' . $sOption . '" is not a valid ' . $sNamespace . ' framework option',
                ]);
                return $this->setFramework($sNamespace, $oProperty);
            }

        } elseif (count($aFrameworks) === 1) {
            $this->oOutput->writeln('Only one ' . $sNamespace . ' framework available: ' . $aFrameworks[0]);
            $iChoice = 0;
        } else {
            $iChoice = $this->choose($sNamespace . ' Framework', $aFrameworks);
        }

        $oProperty = $aFrameworkClasses[$iChoice];

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Configures framework options
     *
     * @param Framework $oFramework The framework to configure
     * @param array     $aOptions   The property to assign the results to
     *
     * @return $this
     */
    private function setFrameworkOptions($oFramework, &$aOptions)
    {
        $aOptions = $oFramework->getOptions();
        foreach ($aOptions as $oOption) {
            //  @todo (Pablo - 2018-12-18) - Allow options to be configured
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
        $this->oOutput->writeln('<comment>Project Name</comment>:  ' . $this->sProjectName);
        $this->oOutput->writeln('<comment>Project Slug</comment>:  ' . $this->sProjectSlug);
        $this->oOutput->writeln('<comment>Directory</comment>:  ' . $this->sDir);
        $this->oOutput->writeln('<comment>Backend Framework</comment>:  ' . $this->oBackendFramework->getName());
        $this->oOutput->writeln('<comment>Frontend Framework</comment>:  ' . $this->oFrontendFramework->getName());
        $this->oOutput->writeln('');
        return $this->confirm('Continue?');
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new project
     *
     * @return $this
     * @throws CannotOpenException
     * @throws CommandFailedException
     * @throws FailedToCreateException
     */
    private function createProject()
    {
        $this->oOutput->writeln('');

        $this
            ->createProjectDir()
            ->installSkeleton()
            ->installFrameworks(
                $this->oBackendFramework,
                $this->oBackendFrameworkOptions,
                $this->oFrontendFramework,
                $this->oFrontendFrameworkOptions
            );

        $this->oOutput->writeln('');
        $this->oOutput->writeln('Project has been configured at <comment>' . $this->sDir . '</comment>');
        $this->oOutput->writeln('Run <comment>make up</comment> to build containers and install frameworks');
        $this->oOutput->writeln('');
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Creates the project directory if it does not exist
     *
     * @return $this
     * @throws FailedToCreateException
     * @throws CommandFailedException
     */
    private function createProjectDir()
    {
        $this->oOutput->write('Creating directory <comment>' . $this->sDir . '</comment> ...');
        if (!mkdir($this->sDir)) {
            throw new FailedToCreateException();
        }
        System::exec('cd "' . $this->sDir . '"');
        $this->oOutput->writeln('<info>done</info>');
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Installs the Docker skeleton
     *
     * @return $this
     * @throws CannotOpenException
     * @throws CommandFailedException
     */
    private function installSkeleton()
    {
        $this->oOutput->write('Installing Docker skeleton ...');

        //  Download skeleton
        $sZipPath = $this->sDir . 'docker.zip';
        file_put_contents($sZipPath, file_get_contents(static::DOCKER_SKELETON));

        //  Extract
        Zip::unzip($sZipPath, $this->sDir, 'skeleton-docker-lamp-master');

        //  Make all the .sh files executable
        $oFinder = new Finder();
        $oFinder->name('*.sh');
        foreach ($oFinder->in($this->sDir) as $oFile) {
            System::exec('chmod +x "' . $oFile->getPath() . '/' . $oFile->getFilename() . '"');
        }

        $this->oOutput->writeln('<info>done</info>');
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Installs the frameworks and configures ENV vars
     *
     * @param Framework $oBackendFramework  The backend framework
     * @param array     $aBackendOptions    The backend framework options
     * @param Framework $oFrontendFramework The frontend framework
     * @param array     $aFrontendOptions   The frontend framework options
     *
     * @return $this
     */
    private function installFrameworks(
        Framework $oBackendFramework,
        array $aBackendOptions,
        Framework $oFrontendFramework,
        array $aFrontendOptions
    ) {
        $this->oOutput->write('Installing backend framework: ' . $oBackendFramework->getName() . ' ... ');
        $oBackendFramework->install($this->sDir, $aBackendOptions, $oFrontendFramework);
        $this->oOutput->writeln('<info>done</info>');

        $this->oOutput->write('Installing frontend framework: ' . $oFrontendFramework->getName() . ' ... ');
        $oFrontendFramework->install($this->sDir, $aFrontendOptions, $oBackendFramework);
        $this->oOutput->writeln('<info>done</info>');

        $this->oOutput->write('Configuring web server environment variables ... ');
        $this->configureWebServerEnvVars($oBackendFramework, $oFrontendFramework);
        $this->oOutput->writeln('<info>done</info>');

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * @param Framework $oBackendFramework  The backend framework
     * @param Framework $oFrontendFramework The frontend framework
     */
    private function configureWebServerEnvVars($oBackendFramework, $oFrontendFramework)
    {
        $aConfig = Yaml::parseFile($this->sDir . 'docker-compose.override.yml');
        if (empty($aConfig['webserver']['environment'])) {
            $aConfig['webserver']['environment'] = [];
        }

        $aConfig['webserver']['environment'] = array_merge(
            $aConfig['webserver']['environment'],
            $oBackendFramework->getEnvVars($oFrontendFramework),
            $oFrontendFramework->getEnvVars($oBackendFramework)
        );

        file_put_contents(
            $this->sDir . 'docker-compose.override.yml',
            Yaml::dump($aConfig, 100)
        );
    }
}
