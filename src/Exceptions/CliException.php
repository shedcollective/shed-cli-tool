<?php

namespace Shed\Cli\Exceptions;

use RuntimeException;

/**
 * Class CliException
 *
 * @package Shed\Cli\Exceptions
 */
class CliException extends RuntimeException
{
    /**
     * Any additional details
     *
     * @var array
     */
    protected $aDetails = [];

    // --------------------------------------------------------------------------

    /**
     * Set additional details
     *
     * @param string[] $aDetails
     */
    public function setDetails(array $aDetails)
    {
        $this->aDetails = $aDetails;
    }

    // --------------------------------------------------------------------------

    /**
     * Return the details array
     *
     * @return string[]
     */
    public function getDetails()
    {
        return $this->aDetails;
    }
}
