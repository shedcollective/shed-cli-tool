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
    public static function exec(string|array $mCommand, array &$aOutput = []): int
    {
        if (is_array($mCommand)) {
            $mCommand = implode(' && ', array_filter($mCommand));
        }

        exec($mCommand, $aOutput, $iExitCode);
        if ($iExitCode) {
            throw new CommandFailedException(
                '"' . $mCommand . '" failed with non-zero exit code: ' . $iExitCode,
                $iExitCode
            );
        }

        return $iExitCode;
    }

    public static function execString(string|array $mCommand): string
    {
        if (is_array($mCommand)) {
            $mCommand = implode(' && ', array_filter($mCommand));
        }

        $output = [];
        self::exec($mCommand, $output);
        return (string) reset($output);
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a particular command exists
     *
     * @param string $sCommand The command to test
     *
     * @return bool
     */
    public static function commandExists(string $sCommand): bool
    {
        $sCommandPath = `which $sCommand`;
        return !empty($sCommandPath);
    }

    // --------------------------------------------------------------------------

    /**
     * Get the host's public IP address
     *
     * @return string|null
     */
    public static function ip(): ?string
    {
        $details = json_decode(file_get_contents('https://ipinfo.io/json'), true);
        return $details['ip'] ?? null;

    }
}
