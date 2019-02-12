<?php

namespace Shed\Cli\Resources\Provider;

final class Account
{
    /**
     * The account's label
     *
     * @var string
     */
    private $sLabel = '';

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
        $this->sLabel  = $sLabel;
        $this->sSecret = $sSecret;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the account's Label property
     *
     * @param string $sLabel the label to set
     *
     * @return $this;
     */
    public function setLabel(string $sLabel): self
    {
        $this->sLabel = $sLabel;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Get the account's Label property
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->sLabel;
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
