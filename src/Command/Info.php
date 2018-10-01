<?php

namespace App\Command;

use App\Helper\Output;

final class Info extends Base
{

    /**
     * Describes what the command does
     */
    const INFO = 'Displays this help message';

    // --------------------------------------------------------------------------

    /**
     * Called when the command is executed
     */
    public function execute()
    {
        Output::line(APP_DESCRIPTION);
        Output::line();
        Output::line('Version:  ' . APP_VERSION);
        Output::line('Home:     ' . APP_HOME);
        Output::line('Issues:   ' . APP_ISSUES);
        Output::line();
        Output::line('<comment>Available commands</comment>');

        $aClasses  = scandir(__DIR__);
        $aCommands = [];

        foreach ($aClasses as $sClass) {

            $sClass     = basename($sClass, '.php');
            $sClassName = 'App\\Command\\' . $sClass;
            $sClass     = strtolower($sClass);

            if ($sClass != 'base') {
                $aCommands[$sClass] = $sClassName::INFO;
            }
        }

        $iLength = max(array_map('strlen', array_keys($aCommands)));

        foreach ($aCommands as $sCommand => $sInfo) {
            Output::line('  <comment>' . str_pad($sCommand, $iLength) . '</comment> - ' . $sInfo);
        }
    }
}
