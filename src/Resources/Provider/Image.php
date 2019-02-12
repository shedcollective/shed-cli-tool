<?php

namespace Shed\Cli\Resources\Provider;

final class Image
{
    /**
     * The image's label
     *
     * @var string
     */
    private $sLabel = '';

    /**
     * The image's slug
     *
     * @var string
     */
    private $sSlug = '';

    // --------------------------------------------------------------------------

    /**
     * Image constructor.
     *
     * @param string $sLabel The image's label
     * @param string $sSlug  The image's slug
     */
    public function __construct(string $sLabel, string $sSlug)
    {
        $this->sLabel = $sLabel;
        $this->sSlug  = $sSlug;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the image's Label property
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
     * Get the image's Label property
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->sLabel;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the image's Slug property
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
     * Get the image's Slug property
     *
     * @return string
     */
    public function getSlug(): string
    {
        return $this->sLabel;
    }
}
