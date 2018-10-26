<?php

namespace App\Command\Project;

use App\Command\Base;
use App\Exceptions\CommandFailed;
use App\Exceptions\Directory\FailedToCreate;
use App\Exceptions\EnvironmentNotValid;
use App\Exceptions\Zip\CannotOpen;
use App\Helper\Directory;
use App\Helper\System;
use App\Interfaces\Framework;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;

final class Create extends Base
{
    /**
     * the URL of the Docker skeleton
     *
     * @var string
     */
    const DOCKER_SKELETON = 'https://github.com/nails/skeleton-docker-lamp/archive/master.zip';

    /**
     * Where to create the project
     *
     * @var string
     */
    private $sDir = null;

    /**
     * The framework to use
     *
     * @var Framework
     */
    private $oFramework = null;

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
                'directory',
                'd',
                InputOption::VALUE_OPTIONAL,
                'The directory to create the project in, if empty then the current working directory is used'
            )
            ->addOption(
                'framework',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Which framework to use: None, Nails, Laravel, or WordPress',
                'NONE'
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Execute the command
     *
     * @return int|null|void
     * @throws CannotOpen
     * @throws CommandFailed
     * @throws EnvironmentNotValid
     * @throws FailedToCreate
     */
    protected function go()
    {
        $this->oOutput->writeln('Setting up a new project');
        $this->checkEnvironment();
        $this->setVariables();
        if ($this->confirmVariables()) {
            $this->createProject();
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Validates that the environment is usable
     *
     * @throws EnvironmentNotValid
     */
    private function checkEnvironment()
    {
        if (!function_exists('exec')) {
            throw new EnvironmentNotValid('Missing function exec()');
        }

        $aRequiredCommands = ['git', 'git-flow', 'composer', 'npm'];
        foreach ($aRequiredCommands as $sRequiredCommand) {
            if (!System::commandExists($sRequiredCommand)) {
                throw new EnvironmentNotValid($sRequiredCommand . ' is not installed');
            }
        }
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
            ->setDirectory()
            ->setFramework();
    }

    // --------------------------------------------------------------------------

    /**
     * Set where to create the project
     *
     * @return $this
     */
    private function setDirectory()
    {
        $this->sDir = $this->ask(
            'Project Directory <info>(Leave blank for current directory)</info>:',
            $this->oInput->getOption('directory'),
            function ($sInput) {
                $sInput = static::prepDirectory($sInput);
                if (!Directory::isEmpty($sInput)) {
                    $this->error([
                        'Directory is not empty',
                        $sInput,
                    ]);
                    return false;
                }

                return true;
            }
        );
        $this->sDir = static::prepDirectory($this->sDir);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Looks up and sets the framework
     *
     * @return $this
     */
    private function setFramework()
    {
        $aFrameworks       = ['No framework'];
        $aFrameworkClasses = [null];

        $sBasePath = BASEPATH . 'src';
        $oFinder   = new Finder();
        $oFinder->files()->in($sBasePath . '/Project/Framework/');
        foreach ($oFinder as $oFile) {

            $sFramework = $oFile->getPath() . '/' . $oFile->getBasename('.php');
            $sFramework = str_replace($sBasePath, 'App', $sFramework);
            $sFramework = str_replace('/', '\\', $sFramework);

            $oFramework          = new $sFramework();
            $aFrameworks[]       = $oFramework->getName();
            $aFrameworkClasses[] = $oFramework;
        }

        $iChoice          = $this->choose('Framework', $aFrameworks);
        $this->oFramework = $aFrameworkClasses[$iChoice];

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Takes a directory path, and if not absolute make it absolute relative to current working directory
     *
     * @param string $sPath The path to inspect
     *
     * @return string
     */
    private static function prepDirectory($sPath)
    {
        if (!preg_match('/^' . preg_quote(DIRECTORY_SEPARATOR, '/') . '/', $sPath)) {
            $sPath = getcwd() . DIRECTORY_SEPARATOR . $sPath;
        }

        if (!preg_match('/' . preg_quote(DIRECTORY_SEPARATOR, '/') . '$/', $sPath)) {
            $sPath = $sPath . DIRECTORY_SEPARATOR;
        }

        return $sPath;
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
        $this->oOutput->writeln('<comment>Directory</comment>:  ' . $this->sDir);
        $this->oOutput->writeln('<comment>Framework</comment>:  ' . ($this->oFramework ? $this->oFramework->getName() : 'none'));
        $this->oOutput->writeln('');
        return $this->confirm('Continue?');
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new project
     *
     * @return $this
     * @throws CannotOpen
     * @throws CommandFailed
     * @throws FailedToCreate
     */
    private function createProject()
    {
        $this->oOutput->writeln('');

        $this
            ->createProjectDir()
            ->installSkeleton()
            ->configureFramework();

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
     * @throws FailedToCreate
     * @throws CommandFailed
     */
    private function createProjectDir()
    {
        $this->oOutput->write('Creating directory <comment>' . $this->sDir . '</comment>');
        if (!mkdir($this->sDir)) {
            throw new FailedToCreate();
        }
        System::exec('cd "' . $this->sDir . '"');
        $this->oOutput->writeln(' ... <info>done</info>');
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Installs the Docker skeleton
     *
     * @return $this
     * @throws CannotOpen
     * @throws CommandFailed
     */
    private function installSkeleton()
    {
        $this->oOutput->write('Installing Docker skeleton');

        //  Download skeleton
        $sZipPath = $this->sDir . 'docker.zip';
        file_put_contents($sZipPath, file_get_contents(static::DOCKER_SKELETON));

        //  Extract
        $oZip = new \ZipArchive();
        if ($oZip->open($sZipPath) === true) {

            $oZip->extractTo($this->sDir);
            $oZip->close();

            System::exec('mv ' . $this->sDir . 'skeleton-docker-lamp-master/* ' . rtrim($this->sDir, '/') . '');
            System::exec('mv ' . $this->sDir . 'skeleton-docker-lamp-master/.[a-z]* ' . rtrim($this->sDir, '/') . '');

        } else {
            throw new CannotOpen('Failed to unzip Docker skeleton');
        }

        //  Make all the .sh files executable
        $oFinder = new Finder();
        $oFinder->name('*.sh');
        foreach ($oFinder->in($this->sDir) as $oFile) {
            System::exec('chmod +x "' . $oFile->getPath() . '/' . $oFile->getFilename() . '"');
        }

        //  Tidy up
        unlink($sZipPath);
        rmdir($this->sDir . 'skeleton-docker-lamp-master');

        $this->oOutput->writeln(' ... <info>done</info>');
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Configures the appropriate Docker file and framework
     *
     * @return $this
     */
    private function configureFramework()
    {
        if ($this->oFramework) {
            $this->oOutput->write('Installing framework');
            $this->oFramework->install($this->sDir);
            $this->oOutput->writeln(' ... <info>done</info>');
        }
        return $this;
    }
}
