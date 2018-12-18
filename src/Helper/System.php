<?php

namespace Shed\Cli\Helper;

use Shed\Cli\Exceptions\System\CommandFailedException;

final class System
{
    /**
     * Executes a system command
     *
     * @param string|array $mCommand The command to execute
     *
     * @throws CommandFailedException
     */
    public static function exec($mCommand)
    {
        if (is_array($mCommand)) {
            $mCommand = implode(' && ', $mCommand);
        }

        exec($mCommand, $aOutput, $iExitCode);
        if ($iExitCode) {
            throw new CommandFailedException('"' . $mCommand . '" failed with non-zero exit code ' . $iExitCode);
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
