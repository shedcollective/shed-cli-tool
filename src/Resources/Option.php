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
     * @return callable|array
     */
    public function getOptions(): array
    {
        if (is_callable($this->mOptions)) {
            return call_user_func($this->mOptions);
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

    /**
     * Return the option's summary callback
     *
     * @param mixed $mValue The selected value
     *
     * @return string
     */
    public function summarise($mValue): string
    {
        if ($this->getType() === static::TYPE_CHOOSE) {
            $aOptions = $this->getOptions();
            return '<comment>' . $this->getLabel() . '</comment>: ' . $aOptions[$mValue];
        } else {
            return '<comment>' . $this->getLabel() . '</comment>: ' . $mValue;
        }
    }
}
