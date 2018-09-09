<?php

namespace App;

use App\Helper\Output;

final class App
{
    //  File names
    const CONFIG_FILE   = '.' . APP_PACKAGE_NAME . 'rc';
    const DEFAULTS_FILE = '.' . APP_PACKAGE_NAME . 'defaults';

    // --------------------------------------------------------------------------

    //  Properties
    private $aConfig    = [];
    private $aArguments = [];
    private $aCommands  = [];

    // --------------------------------------------------------------------------

    /**
     * App constructor.
     *
     * @param array $aArguments The arguments passed to the script
     */
    public function __construct($aArguments)
    {
        //  Parse arguments
        $this->parseArgs($aArguments);

        /**
         * Load configs. There are a few files which will be loaded and can be used
         * to override previous definitions:
         *
         * - Defaults
         * Bundled with the package.
         *
         * - Global defaults
         * Allow defaults to be overridden. This allows for the server admin to
         * customise the defaults for every user on the system whilst still allowing
         * each user to define their own configs
         *
         * - Global user config
         * Allow the user to define a config file in their home directory
         *
         * - Argument config
         * Allow the user to pass in a custom config file path
         */

        $this->loadConfig(__DIR__ . '/../src/defaults.json', true)
            ->loadConfig('/' . self::DEFAULTS_FILE)
            ->loadConfig($_SERVER['HOME'] . '/' . self::CONFIG_FILE)
            ->loadConfig($this->getArg('config') ?: $this->getArg('c'));
    }

    // --------------------------------------------------------------------------

    /**
     * Loads a config file and, if valid, merges in with the main config array
     * @param string $sPath               The file to load
     * @param bool   $bAllowNewProperties Whether the config file can define new properties
     * @return $this
     */
    private function loadConfig($sPath, $bAllowNewProperties = false)
    {
        if (file_exists($sPath)) {
            $sConfig = file_get_contents($sPath);
            $oConfig = json_decode($sConfig);
            if (empty($oConfig)) {
                $oConfig = new \stdClass();
            }
            if (!$bAllowNewProperties) {
                foreach ($this->aConfig as $sKey => $mValue) {
                    if (property_exists($oConfig, $sKey)) {
                        $this->aConfig[$sKey] = $oConfig->{$sKey};
                    }
                }
            } else {
                foreach ($oConfig as $sKey => $mValue) {
                    $this->aConfig[$sKey] = $oConfig->{$sKey};
                }
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Parse and extract valid arguments
     *
     * @param array $aArgs The arguments to parse
     * @return $this
     */
    private function parseArgs($aArgs)
    {
        $aArgs = array_splice($aArgs, 1);
        $aArgs = array_map('trim', $aArgs);

        foreach ($aArgs as $sArg) {
            if (preg_match('/^--(.*)(="?(.*)"?)?$/', $sArg, $aMatches)) {
                $this->aArguments[$aMatches[1]] = !empty($aMatches[3]) ? $aMatches[3] : true;
            } else {
                $this->aCommands[] = $sArg;
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------------


    /**
     * Returns the value for a given config
     *
     * @param string $sKey The config to get
     * @return mixed|null
     */
    public function config($sKey = null)
    {
        if (is_null($sKey)) {
            return $this->aConfig;
        } else {
            return $this->hasConfig($sKey) ? $this->aConfig[$sKey] : null;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Tests whether a config has been specified.
     *
     * @param string $sKey The config to test
     * @return bool
     */
    public function hasConfig($sKey = '')
    {
        return array_key_exists($sKey, $this->aConfig);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the value for a given argument
     *
     * @param string $sKey The argument to get
     * @return mixed|null
     */
    public function getArg($sKey = null)
    {
        if (is_null($sKey)) {
            return $this->aArguments;
        } else {
            return $this->hasArg($sKey) ? $this->aArguments[$sKey] : null;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Tests whether an argument has been specified.
     *
     * @param string $sKey The argument to test
     * @return bool
     */
    public function hasArg($sKey = '')
    {
        return array_key_exists($sKey, $this->aArguments);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a command at a given index
     *
     * @param integer $iIndex The index of the command to get
     * @return mixed|null
     */
    public function command($iIndex = 0)
    {
        return array_key_exists($iIndex, $this->aCommands) ? strtolower($this->aCommands[$iIndex]) : null;
    }

    // --------------------------------------------------------------------------

    /**
     * The main touch point for the app
     */
    public function go()
    {
        ob_start();
        include __DIR__ . '/../src/art.php';
        $sArt = ob_get_contents();
        ob_end_clean();
        Output::line($sArt);

        // --------------------------------------------------------------------------

        $sCommand = ucfirst($this->command()) ?: 'Info';
        $sClass   = 'App\\Command\\' . $sCommand;

        try {
            $oCommand = new $sClass($this);
        } catch (\Exception $e) {
            $oCommand = new Command\Info($this);
        }

        $oCommand->execute();
        Output::line();
    }
}
