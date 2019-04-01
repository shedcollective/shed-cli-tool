<?php

namespace Shed\Cli\Command\Project;

use Shed\Cli\Command;
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

final class Create extends Command
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
    private $aBackendFrameworkOptions = [];

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
    private $aFrontendFrameworkOptions = [];

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure(): void
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
     * @return int
     * @throws CannotOpenException
     * @throws CommandFailedException
     * @throws NotValidException
     * @throws FailedToCreateException
     */
    protected function go(): int
    {
        $this
            ->banner('Setting up a new project')
            ->checkEnvironment()
            ->setVariables();

        if ($this->confirmVariables()) {
            $this->createProject();
        }

        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    /**
     * Validates that the environment is usable
     *
     * @return $this
     * @throws NotValidException
     *
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
                $this->aBackendFrameworkOptions
            )
            ->setFramework(
                'Frontend',
                $this->oFrontendFramework,
                $this->oInput->getOption('frontend-framework')
            )
            ->setFrameworkOptions(
                $this->oFrontendFramework,
                $this->aFrontendFrameworkOptions
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the project name property
     *
     * @return $this
     */
    private function setProjectName(): Create
    {
        $sOption = trim($this->oInput->getOption('name'));
        if (empty($sOption)) {
            $this->sProjectName = $this->ask(
                'Project Name:',
                null,
                [$this, 'validateProjectName']
            );
        } else {
            if ($this->validateProjectName($sOption)) {
                $this->sProjectName = $sOption;
                $this->oOutput->writeln('<comment>Project Name</comment>: ' . $this->sProjectName);
            } else {
                $this->sProjectName = $this->ask(
                    'Project Name:',
                    null,
                    [$this, 'validateProjectName']
                );
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Validate a project name is valid
     *
     * @param string $sProjectName The name to test
     *
     * @return bool
     */
    protected function validateProjectName($sProjectName): bool
    {
        if (empty($sProjectName)) {
            $this->error(array_filter([
                'Project Name is required',
                $sProjectName,
            ]));
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the project slug property
     *
     * @return $this
     */
    private function setProjectSlug(): Create
    {
        $sOption  = trim($this->oInput->getOption('slug'));
        $sDefault = strtolower($this->sProjectName);
        $sDefault = preg_replace('/[^a-z0-9\- ]/', '', $sDefault);
        $sDefault = str_replace(' ', '-', $sDefault);

        if (empty($sOption)) {
            $this->sProjectSlug = $this->ask(
                'Project Slug:',
                $sDefault,
                [$this, 'validateProjectSlug']
            );
        } else {
            if ($this->validateProjectSlug($sOption)) {
                $this->sProjectSlug = $sOption;
                $this->oOutput->writeln('<comment>Project Slug</comment>: ' . $this->sProjectSlug);
            } else {
                $this->sProjectSlug = $this->ask(
                    'Project Slug:',
                    $sDefault,
                    [$this, 'validateProjectSlug']
                );
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Validate a project slug is valid
     *
     * @param string $sProjectSlug The slug to test
     *
     * @return bool
     */
    protected function validateProjectSlug($sProjectSlug): bool
    {
        if (empty($sProjectSlug)) {
            $this->error(array_filter([
                'Project Slug is required',
                $sProjectSlug,
            ]));
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Set where to create the project
     *
     * @return $this
     */
    private function setDirectory(): Create
    {
        $sOption = trim($this->oInput->getOption('directory'));
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
    protected function validateDirectory($sDirectory): bool
    {
        $sDirectory = Directory::resolve($sDirectory);
        if (!Directory::isEmpty($sDirectory)) {
            $this->error(array_filter([
                'Directory is not empty',
                $sDirectory,
            ]));
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Looks up and sets the framework
     *
     * @param string    $sNamespace The namespace of frameworks to use
     * @param Framework $oProperty  The property to assign the framework to
     * @param string    $sOption    The value of the framework CLI option
     *
     * @return $this
     */
    private function setFramework($sNamespace, &$oProperty, $sOption = null): Create
    {
        $aFrameworks           = [];
        $aFrameworksNormalised = [];
        $aFrameworkClasses     = [];

        $sBasePath = BASEPATH . 'src';
        $oFinder   = new Finder();
        $oFinder->files()->in($sBasePath . '/Project/Framework/' . $sNamespace . '/');
        foreach ($oFinder as $oFile) {

            $sClassName = $oFile->getBasename('.php');
            if ($sClassName === 'Command') {
                continue;
            }

            $sFramework = $oFile->getPath() . '/' . $sClassName;
            $sFramework = str_replace($sBasePath, 'Shed\Cli', $sFramework);
            $sFramework = str_replace('/', '\\', $sFramework);

            $oFramework              = new $sFramework();
            $sFrameworkName          = $oFramework->getLabel();
            $aFrameworks[]           = $sFrameworkName;
            $aFrameworksNormalised[] = strtoupper($sFrameworkName);
            $aFrameworkClasses[]     = $oFramework;
        }

        //  Cherry-pick "none" or "static" as the first framework, if it's there
        $aCherries = ['None', 'Static'];
        foreach ($aCherries as $sCherry) {
            $iCherryIndex = array_search($sCherry, $aFrameworks);
            if ($iCherryIndex !== false) {
                break;
            }
        }

        if (isset($iCherryIndex) && $iCherryIndex !== false) {

            $aNoneFrameworks           = array_splice($aFrameworks, $iCherryIndex, 1);
            $aNoneFrameworksNormalised = array_splice($aFrameworksNormalised, $iCherryIndex, 1);
            $aNoneFrameworkClasses     = array_splice($aFrameworkClasses, $iCherryIndex, 1);

            array_unshift($aFrameworks, reset($aNoneFrameworks));
            array_unshift($aFrameworksNormalised, reset($aNoneFrameworksNormalised));
            array_unshift($aFrameworkClasses, reset($aNoneFrameworkClasses));
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
    private function setFrameworkOptions($oFramework, &$aOptions): Create
    {
        foreach ($oFramework->getOptions() as $sKey => $oOption) {

            $sType       = $oOption->getType();
            $sLabel      = $oOption->getLabel();
            $aChoices    = $oOption->getOptions($aOptions);
            $mDefault    = $oOption->getDefault();
            $mValidation = $oOption->getValidation();

            if ($sType === 'ask') {

                $aOptions[$sKey] = $this->ask(
                    $sLabel,
                    $mDefault,
                    $mValidation
                );

            } elseif ($sType === 'choose' && !empty($aChoices)) {

                if (count($aChoices) === 1) {
                    reset($aChoices);
                    $aOptions[$sKey] = key($aChoices);
                } else {
                    $aOptions[$sKey] = $this->choose(
                        $sLabel,
                        $aChoices,
                        $mDefault,
                        $mValidation
                    );
                }
            } else {
                $aOptions[$sKey] = null;
            }

            $oOption->setValue($aOptions[$sKey]);
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
        //  @todo (Pablo - 2019-04-01) - Use $this->keyValueList() somehow
        $this->oOutput->writeln('<comment>Project Name</comment>:  ' . $this->sProjectName);
        $this->oOutput->writeln('<comment>Project Slug</comment>:  ' . $this->sProjectSlug);
        $this->oOutput->writeln('<comment>Directory</comment>:     ' . $this->sDir);

        $this->oOutput->writeln('<comment>Backend Framework</comment>:  ' . $this->oBackendFramework->getLabel());
        foreach ($this->oBackendFramework->getOptions() as $sKey => $oOption) {
            $this->oOutput->writeln(
                $oOption->summarise($this->aBackendFrameworkOptions)
            );
        }

        $this->oOutput->writeln('<comment>Frontend Framework</comment>:  ' . $this->oFrontendFramework->getLabel());
        foreach ($this->oFrontendFramework->getOptions() as $sKey => $oOption) {
            $this->oOutput->writeln(
                $oOption->summarise($this->aFrontendFrameworkOptions)
            );
        }

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
    private function createProject(): Create
    {
        $this->oOutput->writeln('');

        $this
            ->createProjectDir()
            ->installSkeleton()
            ->installFrameworks(
                $this->oBackendFramework,
                $this->aBackendFrameworkOptions,
                $this->oFrontendFramework,
                $this->aFrontendFrameworkOptions
            );

        $this->oOutput->writeln('');
        $this->oOutput->writeln('🎉 Project has been configured at <comment>' . $this->sDir . '</comment>');
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
    private function createProjectDir(): Create
    {
        $this->oOutput->write('📁 Creating directory <comment>' . $this->sDir . '</comment>... ');
        if (!mkdir($this->sDir)) {
            throw new FailedToCreateException();
        }
        System::exec('cd "' . $this->sDir . '"');
        $this->oOutput->writeln('👍');
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
    private function installSkeleton(): Create
    {
        $this->oOutput->write('📦 Installing Docker skeleton... ');

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

        $this->oOutput->writeln('👍');
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
    ): Create {

        $aInstallOptions = [
            'name' => $this->sProjectName,
            'slug' => $this->sProjectSlug,
            'dir'  => $this->sDir,
        ];

        $this->oOutput->write('🔧 Installing backend framework: <info>' . $oBackendFramework->getLabel() . '</info>... ');
        $oBackendFramework->install($this->sDir, $aBackendOptions, $oFrontendFramework, $aInstallOptions);
        $this->oOutput->writeln('👍');

        $this->oOutput->write('🎨 Installing frontend framework: <info>' . $oFrontendFramework->getLabel() . '</info>... ');
        $oFrontendFramework->install($this->sDir, $aFrontendOptions, $oBackendFramework, $aInstallOptions);
        $this->oOutput->writeln('👍');

        $this->oOutput->write('🧐 Configuring web server environment variables... ');
        $this->configureWebServerEnvVars($oBackendFramework, $oFrontendFramework);
        $this->oOutput->writeln('👍');

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Configure the Webserver containers environment variables
     *
     * @param Framework $oBackendFramework  The backend framework
     * @param Framework $oFrontendFramework The frontend framework
     */
    private function configureWebServerEnvVars($oBackendFramework, $oFrontendFramework): void
    {
        static::updateWebserverEnvVars(
            $this->sDir,
            array_merge(
                $oBackendFramework->getEnvVars($oFrontendFramework),
                $oFrontendFramework->getEnvVars($oBackendFramework)
            )
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Save additional environment variables to the webserver's Docker configuration
     *
     * @param string $sPath The path to the docker-compose.override.yml file
     * @param array  $aVars The variables to save
     */
    public static function updateWebserverEnvVars($sPath, array $aVars): void
    {
        $aConfig = Yaml::parseFile($sPath . 'docker-compose.override.yml');
        if (empty($aConfig['webserver']['environment'])) {
            $aConfig['webserver']['environment'] = [];
        }

        $aEnvVars = [];
        array_map(
            function ($sInput) use (&$aEnvVars) {
                list($sKey, $sValue) = explode('=', $sInput, 2);
                $aEnvVars[$sKey] = $sValue;
            },
            $aConfig['webserver']['environment']
        );

        $aEnvVars = array_merge(
            $aEnvVars,
            $aVars
        );


        $aConfig['webserver']['environment'] = [];
        foreach ($aEnvVars as $sKey => $sValue) {
            $aConfig['webserver']['environment'][] = $sKey . '=' . $sValue;
        }

        file_put_contents(
            $sPath . 'docker-compose.override.yml',
            Yaml::dump($aConfig, 100)
        );
    }
}
