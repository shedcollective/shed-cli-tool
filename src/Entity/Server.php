<?php

namespace Shed\Cli\Entity;

use Shed\Cli\Entity;
use Shed\Cli\Entity\Provider\Disk;
use Shed\Cli\Entity\Provider\Image;
use Shed\Cli\Entity\Provider\Region;
use Shed\Cli\Entity\Provider\Size;

final class Server extends Entity
{
    /**
     * The server's ID
     *
     * @var string
     */
    private $sId;

    /**
     * The server's IP
     *
     * @var string
     */
    private $sIp;

    /**
     * The server's domain
     *
     * @var string
     */
    private $sDomain;

    /**
     * The server's disk
     *
     * @var Disk
     */
    private $oDisk;

    /**
     * The server's image
     *
     * @var Image
     */
    private $oImage;

    /**
     * The server's region
     *
     * @var Region
     */
    private $oRegion;

    /**
     * The server's size
     *
     * @var Size
     */
    private $oSize;

    // --------------------------------------------------------------------------

    /**
     * Server constructor.
     *
     * @param string $sLabel  The server's label
     * @param string $sSlug   The server's slug
     * @param string $sId     The server's ID
     * @param string $sIp     The server's IP
     * @param string $sDomain The server's domain
     * @param null|Disk   $oDisk   The server's disk
     * @param null|Image  $oImage  The server's image
     * @param null|Region $oRegion The server's region
     * @param null|Size   $oSize   The server's size
     */
    public function __construct(
        string $sLabel = '',
        string $sSlug = '',
        string $sId = '',
        string $sIp = '',
        string $sDomain = '',
        ?Disk $oDisk = null,
        ?Image $oImage = null,
        ?Region $oRegion = null,
        ?Size $oSize = null
    ) {
        parent::__construct($sLabel, $sSlug);
        $this->sId     = $sId;
        $this->sIp     = $sIp;
        $this->sDomain = $sDomain;
        $this->oDisk   = $oDisk;
        $this->oImage  = $oImage;
        $this->oRegion = $oRegion;
        $this->oSize   = $oSize;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the server's ID
     *
     * @param string $sId The server's ID
     *
     * @return $this
     */
    public function setId(string $sId): self
    {
        $this->sId = $sId;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Get the server's ID
     *
     * @return string
     */
    public function getId(): ?string
    {
        return $this->sId;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the server's IP
     *
     * @param string $sIp The server's IP
     *
     * @return $this
     */
    public function setIp(string $sIp): self
    {
        $this->sIp = $sIp;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Get the server's IP
     *
     * @return string
     */
    public function getIp(): ?string
    {
        return $this->sIp;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the server's domain
     *
     * @param string $sDomain The server's domain
     *
     * @return $this
     */
    public function setDomain(string $sDomain): self
    {
        $this->sDomain = $sDomain;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Get the server's domain
     *
     * @return string
     */
    public function getDomain(): ?string
    {
        return $this->sDomain;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the server's Disk
     *
     * @param Disk $oDisk The server's Disk
     *
     * @return $this
     */
    public function setDisk(Disk $oDisk): self
    {
        $this->oDisk = $oDisk;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Get the server's Disk
     *
     * @return Disk
     */
    public function getDisk(): ?Disk
    {
        return $this->oDisk;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the server's Image
     *
     * @param Image $oImage The server's Image
     *
     * @return $this
     */
    public function setImage(Image $oImage): self
    {
        $this->oImage = $oImage;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Get the server's Image
     *
     * @return Image
     */
    public function getImage(): ?Image
    {
        return $this->oImage;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the server's Region
     *
     * @param Region $oRegion The server's Region
     *
     * @return $this
     */
    public function setRegion(Region $oRegion): self
    {
        $this->oRegion = $oRegion;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Get the server's Region
     *
     * @return Region
     */
    public function getRegion(): ?Region
    {
        return $this->oRegion;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the server's Size
     *
     * @param Size $oSize The server's Size
     *
     * @return $this
     */
    public function setSize(Size $oSize): self
    {
        $this->oSize = $oSize;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Get the server's Size
     *
     * @return Size
     */
    public function getSize(): ?Size
    {
        return $this->oSize;
    }
}
