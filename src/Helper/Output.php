<?php

namespace App\Helper;

use App\Helper\Output\Colors;

class Output {

    private $oColors;

    // --------------------------------------------------------------------------

    public function __construct()
    {
        $this->oColors = new Colors();
    }

    // --------------------------------------------------------------------------

    /**
     * Dumps a line to stdout
     *
     * @param string $sLine The line to write
     * @param bool   $bNewLine Whether to include a trailing slash
     */
    public static function line($sLine = '', $bNewLine = true)
    {
        // --------------------------------------------------------------------------

        //  Add a spot of Color()
        $oColors = new Colors();
        $aTags = [
            'comment' => ['green', null],
            'info'    => ['blue', null],
            'warning' => ['yellow', null],
            'error'   => ['black', 'red']
        ];

        foreach ($aTags as $sTag => $aColors) {
            $sLine = preg_replace_callback(
                '/<' . $sTag . '>(.*?)<\/' . $sTag . '>/',
                function ($aMatches) use ($oColors, $aColors) {
                    list($sForeground, $sBackground) = $aColors;
                    return $oColors->getColoredString($aMatches[1], $sForeground, $sBackground);
                },
                $sLine
            );
        }

        // --------------------------------------------------------------------------

        //  Add a new line if necessary
        $sLine = $bNewLine ? $sLine . "\n" : $sLine;

        fwrite(STDOUT, $sLine);
    }
}
