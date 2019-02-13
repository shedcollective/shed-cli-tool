<?php

namespace Shed\Cli;

abstract class Entity
{
    /**
     * The entity's label
     *
     * @var string
     */
    protected $sLabel = '';

    /**
     * The entity's slug
     *
     * @var string
     */
    protected $sSlug = '';

    // --------------------------------------------------------------------------

    /**
     * Size constructor.
     *
     * @param string $sLabel The entity's label
     * @param string $sSlug  The entity's slug
     */
    public function __construct(string $sLabel = '', string $sSlug = '')
    {
        $this->sLabel = $sLabel;
        $this->sSlug  = $sSlug;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the entity's Label property
     *
     * @param string $sLabel The label to set
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
     * Get the entity's Label property
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->sLabel;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the entity's Slug property
     *
     * @param string $sSlug The slug to set
     *
     * @return $this;
     */
    public function setSlug(string $sSlug): self
    {
        $this->sSlug = $sSlug;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Get the entity's Slug property
     *
     * @return string
     */
    public function getSlug(): string
    {
        return $this->sSlug;
    }
}
