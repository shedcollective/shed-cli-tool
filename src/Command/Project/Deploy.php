<?php

namespace App\Command\Project;

use App\Command\Base;

final class Deploy extends Base
{
    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('project:deploy')
            ->setDescription('Deploy a project')
            ->setHelp('This command will interactively deploy a project to a configured server.');
    }

    // --------------------------------------------------------------------------

    /**
     * Execute the command
     *
     * @return int|null|void
     */
    protected function go()
    {
        //  ...
    }
}
