<?php

namespace App\Helper;

final class Input
{
    /**
     * Requests input from the user
     *
     * @param string   $sQuestion   The input prompt
     * @param string   $sDefault    The default value
     * @param callable $cValidation A validation function
     *
     * @return string
     */
    public static function ask($sQuestion, $sDefault = '', $cValidation = null)
    {
        if ($sDefault) {
            $sQuestion .= ' [' . $sDefault . ']';
        }

        Output::line();
        $sResponse = readline($sQuestion . ': ');

        if ($cValidation && !call_user_func($cValidation, $sResponse ?: $sDefault)) {
            Output::error(['Invalid response']);
            return static::ask($sQuestion, $sDefault, $cValidation);
        }

        return $sResponse;
    }

    // --------------------------------------------------------------------------

    /**
     * Presents the user with options and asks them to choose one
     *
     * @param string   $sQuestion   The input prompt
     * @param array    $aOptions    The options to select from
     * @param callable $cValidation A validation function
     *
     * @return string
     */
    public static function choose($sQuestion, $aOptions, $cValidation = null)
    {
        $aResponse = static::chooseMany($sQuestion, $aOptions, $cValidation);
        if (count($aResponse) > 1) {
            Output::error(['Please select only one option']);
            return static::choose($sQuestion, $aOptions, $cValidation);
        }
        return reset($aResponse);
    }

    // --------------------------------------------------------------------------

    /**
     * Presents the user with options and asks them to choose as many as they wish
     *
     * @param string   $sQuestion   The input prompt
     * @param array    $aOptions    The options to select from
     * @param callable $cValidation A validation function
     *
     * @return array
     */
    public static function chooseMany($sQuestion, $aOptions, $cValidation = null)
    {
        $aNumericOptions = [];
        foreach ($aOptions as $sIndex => $sOption) {
            $aNumericOptions[] = (object) [
                'value' => $sIndex,
                'label' => $sOption,
            ];
        }

        Output::line();
        foreach ($aNumericOptions as $iIndex => $oOption) {
            Output::line('<comment>' . $iIndex . '</comment>: ' . $oOption->label);
        }

        $sResponse = static::ask($sQuestion, null, $cValidation);
        $aResponse = array_map('trim', explode(',', $sResponse));
        $aErrors   = [];
        foreach ($aResponse as &$sResponse) {
            if (!array_key_exists($sResponse, $aNumericOptions)) {
                $aErrors[] = '"' . $sResponse . '" is not a valid option';
            } else {
                $sResponse = $aNumericOptions[$sResponse]->value;
            }
        }

        if (!empty($aErrors)) {
            Output::error($aErrors);
            return static::chooseMany($sQuestion, $aOptions, $cValidation);
        }

        return $aResponse;
    }

    // --------------------------------------------------------------------------

    /**
     * Asks the user to confirm
     *
     * @param string $sQuestion The input prompt
     *
     * @return boolean
     */
    public static function confirm($sQuestion)
    {
        return (bool) preg_match(
            '/y|yes|1/',
            strtolower(static::ask($sQuestion))
        );
    }
}
