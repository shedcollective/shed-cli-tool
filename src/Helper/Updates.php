<?php

namespace Shed\Cli\Helper;

final class Updates
{
    /**
     * The config key to sue to store the time of the last update check
     *
     * @var string
     */
    const CONFIG_KEY_LAST_CHECKED = 'update.checked';

    /**
     * The length of time to wait (in seconds) between update checks
     *
     * @var int
     */
    const TIME_BETWEEN_CHECKS = 86400; // 24 hours

    /**
     * The API endpoint to call for querying tags
     *
     * @var string
     */
    const GITHUB_API_TAGS = 'https://api.github.com/repos/shedcollective/shed-cli-tool/tags';

    // --------------------------------------------------------------------------

    /**
     * The current version of the tool
     *
     * @var string
     */
    private static $sCurrentVersion;

    /**
     * The latest version of the tool
     *
     * @var ?string
     */
    private static $sLatestVersion;

    // --------------------------------------------------------------------------

    /**
     * Checks for updates
     *
     * @param bool $bForce force a check
     *
     * @return bool
     */
    public static function check(bool $bForce = false): bool
    {
        $bUpdateAvailable    = false;
        $iTimeSinceLastCheck = time() - static::getTimeOfLastCheck();

        if ($bForce || $iTimeSinceLastCheck > static::TIME_BETWEEN_CHECKS) {
            if (version_compare(static::getCurrentVersion(), static::getLatestVersion(), '<')) {
                $bUpdateAvailable = true;
            }

            static::setTimeOfLastCheck(time());
        }

        return $bUpdateAvailable;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the time that the tool last checked for an update
     *
     * @return int
     */
    protected static function getTimeOfLastCheck(): int
    {
        return Config::get(static::CONFIG_KEY_LAST_CHECKED) ?? 0;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the time the tool last checked for an update
     *
     * @param int $iTime
     */
    protected static function setTimeOfLastCheck(int $iTime): void
    {
        Config::set(static::CONFIG_KEY_LAST_CHECKED, $iTime);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the current version of the application
     *
     * @return string
     */
    public static function getCurrentVersion(): string
    {
        if (static::$sCurrentVersion) {
            return static::$sCurrentVersion;
        } else {

            $oComposer = json_decode(
                file_get_contents(
                    Directory::normalize(__DIR__ . '/../../composer.json')
                )
            );

            static::$sCurrentVersion = $oComposer->version;
            return static::$sCurrentVersion;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Get the latest version of the app from GitHub
     *
     * @return string
     */
    public static function getLatestVersion(): ?string
    {
        if (static::$sLatestVersion) {
            return static::$sLatestVersion;
        } else {

            $oCurl = curl_init(static::GITHUB_API_TAGS);
            curl_setopt($oCurl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($oCurl, CURLOPT_FAILONERROR, true);
            curl_setopt($oCurl, CURLOPT_USERAGENT, APP_NAME . ' ' . static::getCurrentVersion());

            $sResponse = curl_exec($oCurl);

            if ($sResponse) {

                $aTags   = json_decode($sResponse);
                $oLatest = reset($aTags);

                static::$sLatestVersion = $oLatest->name;
            }

            return static::$sLatestVersion;
        }
    }
}
