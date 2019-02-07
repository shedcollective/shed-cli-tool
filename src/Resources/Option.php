<?php

namespace Shed\Cli\Resources;

final class Option
{
    /**
     * The option types
     */
    const TYPE_ASK    = 'ask';
    const TYPE_CHOOSE = 'choose';

    // --------------------------------------------------------------------------

    /**
     * The option type
     *
     * @var string
     */
    private $sType = Option::TYPE_ASK;

    /**
     * The option label
     *
     * @var string
     */
    private $sLabel = '';

    /**
     * The option default
     *
     * @var mixed
     */
    private $mDefault = null;

    /**
     * The option values (if type is "choose")
     *
     * @var callable|array
     */
    private $mOptions = [];

    /**
     * The validation callback
     *
     * @var callable|null
     */
    private $cValidation = null;

    /**
     * The summary callback
     *
     * @var callable|null
     */
    private $cSummary = null;

    /**
     * The selected value
     *
     * @var mixed
     */
    private $mValue = null;

    // --------------------------------------------------------------------------

    /**
     * Option constructor.
     *
     * @param string         $sType       The option type
     * @param string         $sLabel      The option label
     * @param mixed          $mDefault    The default value
     * @param callable|array $mOptions    The option values (if type is "choose")
     * @param callable|null  $cValidation Validation callback
     * @param callable|null  $cSummary    Summary callback
     */
    public function __construct(
        string $sType = Option::TYPE_ASK,
        string $sLabel = '',
        $mDefault = null,
        $mOptions = [],
        callable $cValidation = null,
        callable $cSummary = null
    ) {
        $this->sType       = $sType;
        $this->sLabel      = $sLabel;
        $this->mDefault    = $mDefault;
        $this->mOptions    = $mOptions;
        $this->cValidation = $cValidation;
        $this->cSummary    = $cSummary;
    }

    // --------------------------------------------------------------------------

    /**
     * Return the option's type
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->sType;
    }

    // --------------------------------------------------------------------------

    /**
     * Return the option's label
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->sLabel;
    }

    // --------------------------------------------------------------------------

    /**
     * Return the option's default
     *
     * @return string
     */
    public function getDefault()
    {
        return $this->mDefault;
    }

    // --------------------------------------------------------------------------

    /**
     * Return the option's values
     *
     * @param array $aOptions The options array
     *
     * @return callable|array
     */
    public function getOptions(array $aOptions = []): ?array
    {
        if (is_callable($this->mOptions)) {
            return call_user_func($this->mOptions, $aOptions);
        } else {
            return $this->mOptions;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Return the option's validation callback
     *
     * @return callable|null
     */
    public function getValidation(): ?callable
    {
        return $this->cValidation;
    }

    // --------------------------------------------------------------------------

    public function setValue($mValue)
    {
        $this->mValue = $mValue;
    }

    // --------------------------------------------------------------------------

    public function getValue()
    {
        return $this->mValue;
    }

    // --------------------------------------------------------------------------

    /**
     * Return the option's summary callback
     *
     * @param array $aOptions The selected options
     *
     * @return string
     */
    public function summarise(array $aOptions = []): string
    {
        if (is_callable($this->cSummary)) {
            $sSummary = call_user_func($this->cSummary, $aOptions);
        } elseif ($this->getType() === static::TYPE_CHOOSE) {
            $aOptions = $this->getOptions($aOptions);
            $sSummary = $aOptions[$this->mValue];
        } else {
            $sSummary = $this->mValue;
        }

        return '<comment>' . $this->getLabel() . '</comment>: ' . $sSummary;
    }
}
