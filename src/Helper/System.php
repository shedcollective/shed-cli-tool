<?php

namespace App\Helper;

use App\Exceptions\CommandFailed;

final class System
{
    /**
     * Executes a system command
     *
     * @param string $sCommand The command to execute
     *
     * @throws CommandFailed
     */
    public static function exec($sCommand)
    {
        $sLastLine = exec($sCommand, $aOutput, $iExitCode);
        if ($iExitCode) {
            throw new CommandFailed(
                '"' . $sCommand . '" failed with non-zero exit code ' . $iExitCode . ' (' . $sLastLine . ')'
            );
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a particular command exists
     *
     * @param string $sCommand The command to test
     *
     * @return bool
     */
    public static function commandExists($sCommand)
    {
        $sCommandPath = `which $sCommand`;
        return !empty($sCommandPath);
    }
}
