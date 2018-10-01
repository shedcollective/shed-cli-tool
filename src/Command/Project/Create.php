<?php

namespace App\Command\Project;

use App\Command\Base;
use App\DigitalOcean\Auth;
use App\DigitalOcean\Compute;
use App\Helper\Debug;
use App\Helper\Input;
use App\Helper\Output;

final class Create extends Base
{
    /**
     * Describes what the command does
     */
    const INFO = 'Creates a new project';

    /**
     * The supported environments
     *
     * @var array
     */
    const ENVIRONMENTS = [
        'PRODUCTION' => 'Production',
        'STAGING'    => 'Staging',
    ];

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
    private $sHostname = null;
    private $sBackendFramework = null;
    private $sFrontendFramework = null;
    private $sContext = null;
    private $sEnvironment = null;
    private $sRegion = null;
    private $sImage = null;
    private $sSize = null;
    private $aTags = [];
    private $aKeys = [];

    // --------------------------------------------------------------------------

    /**
     * Called when the command is executed
     */
    public function execute()
    {
        $this->setVariables();
        if ($this->confirmVariables()) {
            $this
                ->createProject()
                ->createDroplet()
                ->configureDroplet()
                ->createDeployment();
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
        $this->sHostname = Input::ask(
            'Project name (a-z and dashes only)',
            null,
            function ($sResponse) {
                return preg_match('/[^a-z\-]/', $sResponse) === 0;
            }
        );

        $this->sBackendFramework  = Input::choose('Backend Framework', static::BACKEND_FRAMEWORKS);
        $this->sFrontendFramework = Input::choose('Frontend Framework', static::FRONTEND_FRAMEWORKS);

        $this->sContext = Input::choose('Droplet Context', Auth::contextsAsStrings());
        Auth::switchTo($this->sContext);

        $this->sEnvironment = Input::choose('Droplet Environment', static::ENVIRONMENTS);
        $this->sRegion      = Input::choose('Droplet Region', Compute::regionsAsStrings(true));
        $this->sImage       = Input::choose('Droplet Image', Compute::imagesAsStrings());
        $this->sSize        = Input::choose('Droplet Size', Compute::sizesAsStrings());
        $this->aKeys        = Input::chooseMany('Droplet Keys', Compute::sshKeysAsStrings());
        $this->aTags        = [
            $this->sEnvironment,
            $this->sBackendFramework,
            $this->sFrontendFramework,
            Input::ask('Droplet Tags (comma separated)'),
        ];

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
        Output::line();
        Output::line('Does this all look OK?');
        Output::line();
        Output::line('<comment>Project name</comment>:       ' . $this->sHostname);
        Output::line('<comment>Backend Framework</comment>:  ' . $this->sBackendFramework);
        Output::line('<comment>Frontend Framework</comment>: ' . $this->sFrontendFramework);
        Output::line('<comment>Environment</comment>:        ' . $this->sEnvironment);
        Output::line('<comment>Droplet Region</comment>:     ' . $this->sRegion);
        Output::line('<comment>Droplet Image</comment>:      ' . $this->sImage);
        Output::line('<comment>Droplet Size</comment>:       ' . $this->sSize);
        Output::line('<comment>Droplet Keys</comment>:       ' . implode(', ', $this->aKeys));
        Output::line('<comment>Droplet Tags</comment>:       ' . implode(', ', $this->aTags));
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
        //  @todo (Pablo - 2018-09-30) - create folder
        //  @todo (Pablo - 2018-09-30) - pull down docker skeleton
        //  @todo (Pablo - 2018-09-30) - configure backend framework
        //  @todo (Pablo - 2018-09-30) - configure frontend framework
        //  @todo (Pablo - 2018-09-30) - set up git
        Output::line('Done!');
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new droplet
     *
     * @return $this
     */
    private function createDroplet()
    {
        Output::line('Creating droplet...');
        $aResult = Compute\Droplet::create(implode(
            ' ',
            [
                $this->sHostname,
                '--region ' . $this->sRegion,
                '--image ' . $this->sImage,
                '--size ' . $this->sSize,
                '--ssh-keys ' . implode(',', $this->aKeys),
                '--tag-names ' . strtolower(implode(',', $this->aTags)),
                '--wait',
            ]
        ));
        Debug::d($aResult);
        Output::line('Done!');
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * SSH into the droplet and configure it
     *
     * @return $this
     */
    private function configureDroplet()
    {
        Output::line('Creating droplet...');
        //  https://stackoverflow.com/a/4412324/789224
        //  @todo (Pablo - 2018-09-30) - Configure firewall
        //  @todo (Pablo - 2018-09-30) - Set up user
        //  @todo (Pablo - 2018-09-30) - Harden
        Output::line('Done!');

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new deployment
     *
     * @return $this
     */
    private function createDeployment()
    {
        //  @todo (Pablo - 2018-09-30) - Create the project
        return $this;
    }
}
