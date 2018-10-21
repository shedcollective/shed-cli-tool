<?php

namespace App\Command\Project;

use App\Command\Base;
use App\DigitalOcean\Auth;
use App\DigitalOcean\Compute;
use App\Helper\Debug;
use App\Helper\Input;
use App\Helper\Output;

final class Deploy extends Base
{
    /**
     * Describes what the command does
     */
    const INFO = 'Deploys a project';

    /**
     * The supported environments
     *
     * @var array
     */
    const ENVIRONMENTS = [
        'PRODUCTION' => 'Production',
        'STAGING'    => 'Staging',
    ];

    // --------------------------------------------------------------------------

    private $aConfig = [];

    /**
     * The various configurable options
     */
    private $sHostname = null;
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
        Output::line('Deploy this project (<comment>' . $_SERVER['PWD'] . '</comment>)');
        //  @todo (Pablo - 2018-10-06) - Read project config file
        //  @todo (Pablo - 2018-10-06) - Check for existing deployments
        $aDeployments = [];
        if (empty($aDeployments)) {
            $this->newDeployment();
        } else {
            $sServer = Input::choose('Which server would you like to deploy to?', ['' => 'Set up new server'] + $aDeployments);
            if (empty($sServer)) {
                $this->newDeployment();
            } else {
                $this->deploy($sServer);
            }
        }
    }

    // --------------------------------------------------------------------------

    private function newDeployment()
    {
        Output::line('Setting up a new deployment');
        $this->setVariables();
        if ($this->confirmVariables()) {
            $this
                ->createDroplet()
                ->configureDroplet()
                ->createDeployment();
        }
    }

    // --------------------------------------------------------------------------

    private function deploy($sServer)
    {
        //  @todo (Pablo - 2018-10-06) - Create a new deployment
        Output::line('Deploy to "' . $sServer . '"');
    }

    // --------------------------------------------------------------------------

    /**
     * Configures the create command
     *
     * @return $this
     */
    private function setVariables()
    {
        $aContexts = Auth::contextsAsStrings();
        if (!empty($aContexts)) {
            if (count($aContexts) > 1) {
                $this->sContext = Input::choose('Project Context', $aContexts);
            } else {
                $oContext       = reset($aContexts);
                $this->sContext = $oContext->slug;
                Output::line('Using context "' . $this->sContext . '"');
            }
            Auth::switchTo($this->sContext);
        }

        $this->sHostname    = Input::ask(
            'Droplet Hostname (a-z and dashes only)',
            null,
            function ($sResponse) {
                return preg_match('/[^a-z\-]/', $sResponse) === 0;
            }
        );
        $this->sEnvironment = Input::choose('Droplet Environment', static::ENVIRONMENTS);
        $this->sRegion      = Input::choose('Droplet Region', Compute::regionsAsStrings(true));
        $this->sImage       = Input::choose('Droplet Image', Compute::imagesAsStrings());
        $this->sSize        = Input::choose('Droplet Size', Compute::sizesAsStrings());
        $this->aKeys        = Input::chooseMany('Droplet Keys', Compute::sshKeysAsStrings());
        $this->aTags        = array_filter([
            $this->sEnvironment,
            array_key_exists('backend.framework', $this->aConfig) ? $this->aConfig['backend.framework'] : null,
            array_key_exists('frontend.framework', $this->aConfig) ? $this->aConfig['frontend.framework'] : null,
            Input::ask('Droplet Tags (comma separated)'),
        ]);
        $this->aTags        = array_map('strtolower', $this->aTags);

        //  @todo (Pablo - 2018-10-06) - Enable backups?

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
        Output::line('<comment>Project name</comment>:   ' . $this->sHostname);
        Output::line('<comment>Environment</comment>:    ' . $this->sEnvironment);
        Output::line('<comment>Droplet Region</comment>: ' . $this->sRegion);
        Output::line('<comment>Droplet Image</comment>:  ' . $this->sImage);
        Output::line('<comment>Droplet Size</comment>:   ' . $this->sSize);
        Output::line('<comment>Droplet Keys</comment>:   ' . implode(', ', $this->aKeys));
        Output::line('<comment>Droplet Tags</comment>:   ' . implode(', ', $this->aTags));
        Output::line();

        return Input::confirm('Continue?');
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
        Output::line('Configuring droplet...');
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
        Output::line('Configuring deployment...');
        //  @todo (Pablo - 2018-09-30) - Create a deployment
        Output::line('Done!');
        return $this;
    }
}
