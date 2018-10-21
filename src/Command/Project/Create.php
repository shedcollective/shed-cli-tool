<?php

namespace App\Command\Project;

use App\Command\Base;
use App\Helper\Directory;
use App\Helper\Input;
use App\Helper\Output;

final class Create extends Base
{
    /**
     * Describes what the command does
     */
    const INFO = 'Creates a new project';

    /**
     * The URL for the docker repository
     */
    const DOCKER_URL = 'https://github.com/nails/skeleton-docker-lamp/archive/master.zip';

    /**
     * The supported frontend frameworks
     *
     * @var array
     */
    const FRONTEND_FRAMEWORKS = [
        'VANILLA' => 'Vanilla',
        'VUE'     => 'Vue',
        'REACT'   => 'React',
    ];

    /**
     * The supported backend environments
     *
     * @var array
     */
    const BACKEND_FRAMEWORKS = [
        'NAILS'     => 'Nails',
        'LARAVEL'   => 'Laravel',
        'WORDPRESS' => 'WordPress',
    ];

    // --------------------------------------------------------------------------

    /**
     * The various configurable options
     */
    private $sDir = null;
    private $sRepo = null;
    private $sBackendFramework = null;
    private $sFrontendFramework = null;

    // --------------------------------------------------------------------------

    /**
     * Called when the command is executed
     */
    public function execute()
    {
        Output::line('Setting up a new project');
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
        $this->sDir = Input::ask(
            'Project Directory (Leave blank for current directory)',
            null,
            function ($sInput) {
                $sInput = static::prepDirectory($sInput);
                if (!Directory::isEmpty($sInput)) {
                    Output::error([
                        'Directory is not empty',
                        $sInput,
                    ]);
                    return false;
                }

                return true;
            }
        );

        $this->sDir = static::prepDirectory($this->sDir);

        $this->sRepo              = Input::ask('Git repository (leave blank to initiate new repo)');
        $this->sBackendFramework  = Input::choose('Backend Framework', static::BACKEND_FRAMEWORKS);
        $this->sFrontendFramework = Input::choose('Frontend Framework', static::FRONTEND_FRAMEWORKS);

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
        Output::line();
        Output::line('Does this all look OK?');
        Output::line();
        Output::line('<comment>Directory</comment>:  ' . $this->sDir);
        Output::line('<comment>Git repository</comment>:  ' . ($this->sRepo ?: '<none>'));
        Output::line('<comment>Backend Framework</comment>:  ' . $this->sBackendFramework);
        Output::line('<comment>Frontend Framework</comment>: ' . $this->sFrontendFramework);
        Output::line();

        return Input::confirm('Continue?');
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new project
     *
     * @return $this
     */
    private function createProject()
    {
        Output::line('Creating project...');
        $this
            ->createProjectDir()
            ->configureGit()
            ->installSkeleton()
            ->configureBackend()
            ->configurefrontend();
        Output::line('Done!');
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
