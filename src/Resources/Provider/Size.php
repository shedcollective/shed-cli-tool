<?php

namespace Shed\Cli\Resources\Provider;

final class Size
{
    /**
     * The size's label
     *
     * @var string
     */
    private $sLabel = '';

    /**
     * The size's slug
     *
     * @var string
     */
    private $sSlug = '';

    // --------------------------------------------------------------------------

    /**
     * Size constructor.
     *
     * @param string $sLabel The size's label
     * @param string $sSlug  The size's slug
     */
    public function __construct(string $sLabel, string $sSlug)
    {
        $this->sLabel = $sLabel;
        $this->sSlug  = $sSlug;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the size's Label property
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
     * Get the size's Label property
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->sLabel;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the size's Slug property
     *
     * @param string $sSlug the label to set
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
     * Get the size's Slug property
     *
     * @return string
     */
    public function getSlug(): string
    {
        return $this->sLabel;
    }
}
