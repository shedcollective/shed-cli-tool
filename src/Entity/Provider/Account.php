<?php

namespace Shed\Cli\Entity\Provider;

use Shed\Cli\Entity;

final class Account extends Entity
{
    /**
     * The account's secret
     *
     * @var string
     */
    private $sSecret = '';

    // --------------------------------------------------------------------------

    /**
     * Account constructor.
     *
     * @param string $sLabel  The account's label
     * @param string $sSecret The account's secret
     */
    public function __construct(string $sLabel, string $sSecret)
    {
        parent::__construct($sLabel);
        $this->sSecret = $sSecret;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the account's Secret property
     *
     * @param string $sSecret the label to set
     *
     * @return $this;
     */
    public function setSecret(string $sSecret): self
    {
        $this->sSecret = $sSecret;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Get the account's Secret property
     *
     * @return string
     */
    public function getSecret(): string
    {
        return $this->sSecret;
    }
}
