<?php

namespace App\Command\Project;

use App\Command\Base;
use App\Helper\Directory;
use App\Interfaces\Framework;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;

final class Create extends Base
{
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
     * The frontend framework to use
     *
     * @var Framework
     */
    private $oFrontendFramework = null;

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
                'backend',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Which back-end framework to use: None, Nails, Laravel, or WordPress',
                'NONE'
            )
            ->addOption(
                'frontend',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Which front-end framework to use: None, Vue, or React',
                'NONE'
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Execute the command
     *
     * @return int|null|void
     */
    protected function go()
    {
        $this->oOutput->writeln('Setting up a new project');
        $this->setVariables();
        if ($this->confirmVariables()) {
            $this->createProject();
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
            ->setBackend()
            ->setFrontend();
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
     * Set which backend framework to use
     *
     * @return $this
     */
    private function setBackend()
    {
        return $this->setFramework('Backend', $this->oBackendFramework);
    }

    // --------------------------------------------------------------------------

    /**
     * Set which frontend framework to use
     *
     * @return $this
     */
    private function setFrontend()
    {
        return $this->setFramework('Frontend', $this->oFrontendFramework);
    }

    // --------------------------------------------------------------------------

    /**
     * Looks up and sets a framework
     *
     * @param string    $sType     The type of framework being selected
     * @param Framework $oSelected The variable to assign the selected framework to
     *
     * @return $this
     */
    private function setFramework($sType, &$oSelected)
    {
        $sType             = ucfirst(strtolower($sType));
        $aFrameworks       = ['No framework'];
        $aFrameworkClasses = [null];

        $sBasePath = BASEPATH . 'src';
        $oFinder   = new Finder();
        $oFinder->files()->in($sBasePath . '/Project/Framework/' . $sType);
        foreach ($oFinder as $oFile) {

            $sFramework = $oFile->getPath() . '/' . $oFile->getBasename('.php');
            $sFramework = str_replace($sBasePath, 'App', $sFramework);
            $sFramework = str_replace('/', '\\', $sFramework);

            $oFramework          = new $sFramework();
            $aFrameworks[]       = $oFramework->getName();
            $aFrameworkClasses[] = $oFramework;
        }

        $iChoice = $this->choose(
            $sType . ' Framework',
            $aFrameworks
        );

        $oSelected = $aFrameworkClasses[$iChoice];

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
        $this->oOutput->writeln('<comment>Backend Framework</comment>:  ' . $this->oBackendFramework->getName());
        $this->oOutput->writeln('<comment>Frontend Framework</comment>: ' . $this->oFrontendFramework->getName());
        $this->oOutput->writeln('');
        return $this->confirm('Continue?');
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new project
     *
     * @return $this
     */
    private function createProject()
    {
        $this->oOutput->writeln('Creating project...');
        $this
            ->createProjectDir()
            ->configureGit()
            ->installSkeleton()
            ->configureBackend()
            ->configurefrontend();
        $this->oOutput->writeln('Done!');
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Creates the project directory if it does not exist
     *
     * @return $this
     */
    private function createProjectDir()
    {
        //  @todo (Pablo - 2018-10-06) - create directory if it does not exist
        return $this;
    }

    /**
     * Ensures git is configured properly for the project
     *
     * @return $this
     */
    private function configureGit()
    {
        //  @todo (Pablo - 2018-10-06) - pull down repository, check if bare
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Installs the Docker skeleton
     *
     * @return $this
     */
    private function installSkeleton()
    {
        //  @todo (Pablo - 2018-10-06) - Download Docker skeleton
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Configures the appropriate Docker file and framework
     *
     * @return $this
     */
    private function configureBackend()
    {
        //  @todo (Pablo - 2018-10-06) - configure backend framework
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Configures the appropriate front-end framework
     *
     * @return $this
     */
    private function configureFrontend()
    {
        //  @todo (Pablo - 2018-10-06) - configure frontend framework
        return $this;
    }
}
