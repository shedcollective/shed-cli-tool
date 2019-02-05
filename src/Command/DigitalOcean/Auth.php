<?php

namespace Shed\Cli\Command\DigitalOcean;

use Shed\Cli\Command\Base;
use Shed\Cli\Exceptions\Environment\NotValidException;
use Shed\Cli\Helper\Config;
use Shed\Cli\Helper\Debug;
use Shed\Cli\Helper\System;
use Shed\Cli\Interfaces\Infrastructure;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;

final class Auth extends Base
{
    /**
     * Config key containing accounts
     *
     * @var string
     */
    const CONFIG_ACCOUNTS_KEY = 'server.infrastructure.digitalocean.accounts';

    /**
     * Config label/token separator
     *
     * @var string
     */
    const CONFIG_ACCOUNTS_SEPARATOR = ':';

    // --------------------------------------------------------------------------

    /**
     * The label
     *
     * @var string
     */
    private $sLabel = null;

    /**
     * The token
     *
     * @var string
     */
    private $sToken = null;

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('digitalocean:auth')
            ->setDescription('Manage authenticated DigitalOcean accounts')
            ->setHelp('This command allows for the configuration of DigitalOcean access tokens.')
            ->addArgument(
                'account',
                InputArgument::OPTIONAL,
                'View the token for an existing account'
            )
            ->addOption(
                'label',
                'l',
                InputOption::VALUE_OPTIONAL,
                'The label to give the token'
            )
            ->addOption(
                'token',
                't',
                InputOption::VALUE_OPTIONAL,
                'The token'
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Execute the command
     *
     * @return int
     */
    protected function go(): int
    {
        //  @todo (Pablo - 2019-02-05) - Support deleting tokens
        //  @todo (Pablo - 2019-02-05) - Support viewing tokens
        $this
            ->banner('Configuring new Digital Ocean account')
            ->setVariables();

        if ($this->confirmVariables()) {
            $this->addAccount();
        }


        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    /**
     * Configures the create command
     *
     * @return $this
     */
    private function setVariables(): Auth
    {
        return $this
            ->setLabel()
            ->setToken();
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the label property
     *
     * @return $this
     */
    private function setLabel(): Auth
    {
        //  @todo (Pablo - 2019-02-04) - Do not allow empty value
        $sOption = $this->oInput->getOption('label');
        if (empty($sOption)) {
            $this->sLabel = $this->ask(
                'Account label:',
                null,
                [$this, 'validateLabel']
            );
        } else {
            if ($this->validateLabel($sOption)) {
                $this->sLabel = $sOption;
            } else {
                $this->sLabel = $this->ask(
                    'Account label:',
                    null,
                    [$this, 'validateLabel']
                );
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the token property
     *
     * @return $this
     */
    private function setToken(): Auth
    {
        //  @todo (Pablo - 2019-02-04) - Do not allow empty value
        $sOption = $this->oInput->getOption('token');
        if (empty($sOption)) {
            $this->sToken = $this->ask(
                'Access token:',
                null,
                [$this, 'validateToken']
            );
        } else {
            if ($this->validateToken($sOption)) {
                $this->sToken = $sOption;
            } else {
                $this->sToken = $this->ask(
                    'Access token:',
                    null,
                    [$this, 'validateToken']
                );
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Validate a label is valid
     *
     * @param string $sLabel The label to test
     *
     * @return bool
     */
    protected function validateLabel($sLabel): bool
    {
        //  @todo (Pablo - 2019-02-05) - Not an argument name (e.g delete)
        //  @todo (Pablo - 2019-02-05) - valid characters only [a-zA-Z0-9 \-]
        //  @todo (Pablo - 2019-02-05) - Does not contain static::CONFIG_ACCOUNTS_SEPARATOR
        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Validate a token is valid
     *
     * @param string $sToken The token to test
     *
     * @return bool
     */
    protected function validateToken($sToken): bool
    {
        //  @todo (Pablo - 2019-02-05) - Not already in use
        //  @todo (Pablo - 2019-02-05) - Works with API
        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Confirms the selected options
     *
     * @return bool
     */
    private function confirmVariables()
    {
        $this->oOutput->writeln('');
        $this->oOutput->writeln('Does this all look OK?');
        $this->oOutput->writeln('');
        $this->oOutput->writeln('<comment>Account Label</comment>: ' . $this->sLabel);
        $this->oOutput->writeln('<comment>Access Token</comment>:  ' . $this->sToken);
        $this->oOutput->writeln('');
        return $this->confirm('Continue?');
    }

    // --------------------------------------------------------------------------

    /**
     * Save the token
     *
     * @return $this
     */
    private function addAccount(): Auth
    {
        $aAccounts   = Config::get(static::CONFIG_ACCOUNTS_KEY);
        $aAccounts[] = $this->sLabel . static::CONFIG_ACCOUNTS_SEPARATOR . $this->sToken;
        Config::set(static::CONFIG_ACCOUNTS_KEY, $aAccounts);

        $this->oOutput->writeln('');
        $this->oOutput->writeln('🎉 Saved credentials for  <info>' . $this->sLabel . '</info>');
        $this->oOutput->writeln('');
        return $this;
    }
}
