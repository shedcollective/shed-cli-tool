<?php

namespace Shed\Cli\Resources\Provider;

final class Region
{
    /**
     * The region's label
     *
     * @var string
     */
    private $sLabel = '';

    /**
     * The region's slug
     *
     * @var string
     */
    private $sSlug = '';

    // --------------------------------------------------------------------------

    /**
     * Region constructor.
     *
     * @param string $sLabel The region's label
     * @param string $sSlug  The region's label
     */
    public function __construct(string $sLabel, string $sSlug)
    {
        $this->sLabel = $sLabel;
        $this->sSlug  = $sSlug;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the region's Label property
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
     * Get the region's Label property
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->sLabel;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the region's Slug property
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
     * Get the region's Slug property
     *
     * @return string
     */
    public function getSlug(): string
    {
        return $this->sLabel;
    }
}
