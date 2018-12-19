<?php

namespace Shed\Cli\Helper;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

final class Colors
{
    /**
     * Adds additional styles to the output
     *
     * @param OutputInterface $oOutput The output interface
     */
    public static function setStyles(OutputInterface $oOutput)
    {
        $oWarningStyle = new OutputFormatterStyle('white', 'yellow');
        $oOutput->getFormatter()->setStyle('warning', $oWarningStyle);
    }
}
