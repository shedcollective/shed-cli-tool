<?php

namespace App\Command;

final class Project extends Base
{
    /**
     * Describes what the command does
     */
    const INFO = 'Manage Projects (commands: create, deploy';

    // --------------------------------------------------------------------------

    /**
     * Called when the command is executed
     *
     * @throws \Exception
     */
    public function execute()
    {
        //  @todo (Pablo - 2018-09-30) - Find a nicer way of checking the argument (currently could pass two, e.g. `shed project deploy create`)
        if ($this->oApp->hasArg('create')) {
            $this->oApp->execute('Project\\Create');
        } elseif ($this->oApp->hasArg('deploy')) {
            $this->oApp->execute('Project\\Deploy');
        } else {
            throw new \Exception('Invalid project command');
        }
    }
}
