<?php

namespace Shed\Cli\Command\DigitalOcean;

use DigitalOceanV2\Adapter\BuzzAdapter;
use DigitalOceanV2\DigitalOceanV2;
use Shed\Cli\Command\Base;
use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Helper\Config;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

final class Auth extends Base
{
    /**
     * Config key containing accounts
     *
     * @var string
     */
    const CONFIG_ACCOUNTS_KEY = 'server.infrastructure.digitalocean.accounts';

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

    /**
     * The verified DO account
     *
     * @var \stdClass
     */
    private $oAccount;

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
                'action',
                InputArgument::OPTIONAL,
                '[view] or [delete] an existing token; use with --label or --token'
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
        switch ($this->oInput->getArgument('action')) {
            case 'view':
                $this->viewToken();
                break;
            case 'delete':
                $this->deleteToken();
                break;
            default:
                $this->addToken();
                break;
        }

        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    /**
     * View token details
     */
    private function viewToken(): void
    {
        $oAccounts = static::getAccounts();
        $sLabel    = trim($this->oInput->getOption('label'));
        $sToken    = trim($this->oInput->getOption('token'));

        try {

            if (!empty($sLabel)) {

                if (!property_exists($oAccounts, $sLabel)) {
                    throw new CommandFailedException('"' . $sLabel . '" is not a registered account');
                } else {
                    $sToken = $oAccounts->{$sLabel};
                }

                $this->oOutput->writeln('<comment>Label</comment>: ' . $sLabel);
                $this->oOutput->writeln('<comment>Token</comment>: ' . $sToken);

            } elseif (!empty($sToken)) {

                if (!in_array($sToken, (array) $oAccounts)) {
                    throw new CommandFailedException('"' . $sToken . '" is not a registered access token');
                } else {
                    $sLabel = array_search($sToken, (array) $oAccounts);
                }

                $this->oOutput->writeln('<comment>Label</comment>: ' . $sLabel);
                $this->oOutput->writeln('<comment>Token</comment>: ' . $sToken);

            } else {
                foreach ($oAccounts as $sLabel => $sToken) {
                    $this->oOutput->writeln('');
                    $this->oOutput->writeln('<comment>Label</comment>: ' . $sLabel);
                    $this->oOutput->writeln('<comment>Token</comment>: ' . $sToken);
                }
            }

            $this->oOutput->writeln('');

        } catch (CommandFailedException $e) {
            $this->error([$e->getMessage()]);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Add a token
     */
    private function deleteToken(): void
    {
        $oAccounts = static::getAccounts();
        $sLabel    = trim($this->oInput->getOption('label'));
        $sToken    = trim($this->oInput->getOption('token'));

        try {

            if (!empty($sLabel)) {

                if (!property_exists($oAccounts, $sLabel)) {
                    throw new CommandFailedException('"' . $sLabel . '" is not a registered account');
                } else {
                    $sToken = $oAccounts->{$sLabel};
                }

            } elseif (!empty($sToken)) {

                if (!in_array($sToken, (array) $oAccounts)) {
                    throw new CommandFailedException('"' . $sToken . '" is not a registered access token');
                } else {
                    $sLabel = array_search($sToken, (array) $oAccounts);
                }

            } else {
                throw new CommandFailedException('Must specify an account label or token to look up');
            }

            $this->oOutput->writeln('');
            $this->oOutput->writeln('You are about to delete the following access token:');
            $this->oOutput->writeln('<comment>Label</comment>: ' . $sLabel);
            $this->oOutput->writeln('<comment>Token</comment>: ' . $sToken);
            $this->oOutput->writeln('');

            if ($this->confirm('Continue?')) {
                unset($oAccounts->{$sLabel});
                Config::set(static::CONFIG_ACCOUNTS_KEY, $oAccounts);
            }

        } catch (CommandFailedException $e) {
            $this->error([$e->getMessage()]);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Add a token
     */
    private function addToken(): void
    {
        $this
            ->banner('Configuring new Digital Ocean account')
            ->setVariables();

        if ($this->confirmVariables()) {
            $this->addAccount();
        }
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
        $sLabel    = trim($sLabel);
        $sPattern  = '[^a-zA-Z0-0 \-]';
        $aReserved = [];

        if (preg_match('/' . $sPattern . '/', $sLabel)) {
            $this->error([
                '"' . $sLabel . '" contains invalid characters',
                $sPattern,
            ]);
            return false;
        } elseif (in_array($sLabel, $aReserved)) {
            $this->error([
                '"' . $sLabel . '" is a reserved word',
                implode(', ', $aReserved),
            ]);
            return false;
        }

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
        $sToken    = trim($sToken);
        $oAccounts = static::getAccounts();
        $sKey      = array_search($sToken, (array) $oAccounts);

        if ($sKey !== false && $sKey !== trim($this->sLabel)) {
            $this->error([
                'Token is already in use for account "' . $sKey . '"',
            ]);
            return false;
        }

        //  Confirm auth code works
        try {

            $oAdapter       = new BuzzAdapter($sToken);
            $oDigitalOcean  = new DigitalOceanV2($oAdapter);
            $oAccount       = $oDigitalOcean->account();
            $this->oAccount = $oAccount->getUserInformation();

        } catch (\Exception $e) {
            $this->error([$e->getMessage(), $sToken]);
            return false;
        }
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
        $this->oOutput->writeln('<comment>Account Email</comment>: ' . $this->oAccount->email);
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
        $aAccounts                = (array) static::getAccounts();
        $aAccounts[$this->sLabel] = $this->sToken;
        ksort($aAccounts);
        Config::set(static::CONFIG_ACCOUNTS_KEY, (object) $aAccounts);

        $this->oOutput->writeln('');
        $this->oOutput->writeln('🎉 Saved credentials for  <info>' . $this->sLabel . '</info>');
        $this->oOutput->writeln('');
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the accounts object
     *
     * @return \stdClass
     */
    public static function getAccounts(): \stdClass
    {
        return Config::get(Auth::CONFIG_ACCOUNTS_KEY) ?: (object) [];
    }
}
