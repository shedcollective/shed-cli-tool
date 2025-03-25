<?php

namespace Shed\Cli\Entity\Provider;

use Shed\Cli\Entity;

final class Account extends Entity
{
    /**
     * The account's token
     *
     * @var string
     */
    private $sToken = '';

    // --------------------------------------------------------------------------

    /**
     * Account constructor.
     *
     * @param string $sLabel The account's label
     * @param string $sToken The account's token
     */
    public function __construct(string $sLabel, string $sToken)
    {
        parent::__construct($sLabel);
        $this->sToken = $sToken;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the account's Token property
     *
     * @param string $sToken the label to set
     *
     * @return $this
     */
    public function setToken(string $sToken): self
    {
        $this->sToken = $sToken;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Get the account's Token property
     *
     * @return string
     */
    public function getToken(): string
    {
        return $this->sToken;
    }
}
