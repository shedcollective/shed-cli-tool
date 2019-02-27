<?php

namespace Shed\Cli\Helper;

final class Config
{
    /**
     * The config directory
     *
     * @var string
     */
    const CONFIG_DIR = '~' . DIRECTORY_SEPARATOR . '.shedrc' . DIRECTORY_SEPARATOR;

    /**
     * The config file
     *
     * @var string
     */
    const CONFIG_FILE = 'config.json';

    // --------------------------------------------------------------------------

    /**
     * The config object
     *
     * @var \stdClass
     */
    static $oConfig;

    // --------------------------------------------------------------------------

    /**
     * Return the absolute path of the config file
     *
     * @return string
     */
    private static function getConfigPath(): string
    {
        $sPath = Directory::resolve(static::CONFIG_DIR);

        if (!is_dir($sPath)) {
            mkdir($sPath, 0700, true);
        }

        return $sPath . static::CONFIG_FILE;
    }

    // --------------------------------------------------------------------------

    /**
     * Load the config file into memory
     */
    public static function loadConfig(): void
    {
        $sConfigFile = static::getConfigPath();
        if (file_exists($sConfigFile)) {
            static::$oConfig = json_decode(file_get_contents($sConfigFile));
        } else {
            touch($sConfigFile);
        }

        if (is_null(static::$oConfig)) {
            static::$oConfig = (object) [];
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of a particular config item
     *
     * @param string $sProperty The property to return
     *
     * @return mixed
     */
    public static function get(string $sProperty)
    {
        return property_exists(static::$oConfig, $sProperty) ? static::$oConfig->{$sProperty} : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Set a particular config value
     *
     * @param string $sProperty The property to set
     * @param mixed  $mValue    the value to set
     */
    public static function set(string $sProperty, $mValue): void
    {
        static::$oConfig->{$sProperty} = $mValue;

        $sConfigFile = static::getConfigPath();
        file_put_contents($sConfigFile, json_encode(static::$oConfig, JSON_PRETTY_PRINT));
    }
}
