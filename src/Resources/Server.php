<?php

namespace Shed\Cli\Resources;

final class Server
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

    // --------------------------------------------------------------------------

    public function __construct(
        $sId = null,
        string $sIp = null
    ) {
        $this->sId = $sId;
        $this->sIp = $sIp;
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
}
