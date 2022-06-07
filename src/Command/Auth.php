<?php

namespace Shed\Cli\Command;

use Shed\Cli\Command;
use Shed\Cli\Entity\Provider\Account;
use Shed\Cli\Exceptions\Auth\AccountNotFoundException;
use Shed\Cli\Helper\Config;
use Shed\Cli\Exceptions\System\CommandFailedException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

abstract class Auth extends Command
{
    /**
     * The third party slug
     *
     * @var string
     */
    const SLUG = '';

    /**
     * The third party name
     *
     * @var string
     */
    const LABEL = '';


    /**
     * The question for asking the account label
     *
     * @var string
     */
    const QUESTION_LABEL = 'Account Label';


    /**
     * The question for asking the account token
     *
     * @var string
     */
    const QUESTION_TOKEN = 'Account Token';

    // --------------------------------------------------------------------------

    /**
     * The label
     *
     * @var string
     */
    protected $sLabel = null;

    /**
     * The token
     *
     * @var string
     */
    protected $sToken = null;

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('auth:' . static::SLUG)
            ->setDescription('Manage ' . static::LABEL . ' accounts')
            ->setHelp('This command allows for the configuration of ' . static::LABEL . ' credentials.')
            ->addArgument(
                'action',
                InputArgument::OPTIONAL,
                '[help] for information on how to generate credentials, [add] new credentials, [view] or [delete] existing credentials; use with --label or --token'
            )
            ->addOption(
                'label',
                'l',
                InputOption::VALUE_OPTIONAL,
                'The ' . static::QUESTION_LABEL
            )
            ->addOption(
                'token',
                't',
                InputOption::VALUE_OPTIONAL,
                'The ' . static::QUESTION_TOKEN
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
            case 'help':
                $this->help();
                break;
            case 'view':
                $this->view();
                break;
            case 'delete':
                $this->delete();
                break;
            case 'add':
            default:
                $this->add();
                break;
        }

        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    /**
     * Show help on how to generate credentials
     */
    abstract protected function help(): void;

    // --------------------------------------------------------------------------

    /**
     * View token details
     */
    protected function view(): void
    {
        $sLabel = trim($this->oInput->getOption('label') ?? '');
        $sToken = trim($this->oInput->getOption('token') ?? '');

        try {

            if (!empty($sLabel)) {

                $oAccount = self::getAccountByLabel($sLabel);
                $this->keyValueList([
                    static::QUESTION_LABEL => $oAccount->getLabel(),
                    static::QUESTION_TOKEN => $oAccount->getToken(),
                ]);

            } elseif (!empty($sToken)) {

                $oAccount = self::getAccountByToken($sToken);
                $this->keyValueList([
                    static::QUESTION_LABEL => $oAccount->getLabel(),
                    static::QUESTION_TOKEN => $oAccount->getToken(),
                ]);

            } else {
                foreach (static::getAccounts() as $oAccount) {
                    $this->keyValueList([
                        static::QUESTION_LABEL => $oAccount->getLabel(),
                        static::QUESTION_TOKEN => $oAccount->getToken(),
                    ]);
                }
            }

            $this->oOutput->writeln('');

        } catch (CommandFailedException $e) {
            $this->error([$e->getMessage()]);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Delete a token
     */
    protected function delete(): void
    {
        $sLabel = trim($this->oInput->getOption('label') ?? '');
        $sToken = trim($this->oInput->getOption('token') ?? '');

        try {

            if (!empty($sLabel)) {

                $oAccount = self::getAccountByLabel($sLabel);

            } elseif (!empty($sToken)) {

                $oAccount = self::getAccountByToken($sToken);

            } else {
                throw new CommandFailedException(
                    'Must specify an ' . static::QUESTION_LABEL . ' or ' . static::QUESTION_TOKEN . ' to look up'
                );
            }

            $this->oOutput->writeln('');
            $this->oOutput->writeln('You are about to delete the following account:');
            $this->keyValueList([
                static::QUESTION_LABEL => $oAccount->getLabel(),
                static::QUESTION_TOKEN => $oAccount->getToken(),
            ]);

            if ($this->confirm('Continue?')) {
                static::deleteAccount($oAccount);
                $this->oOutput->writeln('');
                $this->oOutput->writeln('🎉 Deleted account <info>' . $oAccount->getLabel() . '</info>');
                $this->oOutput->writeln('');
            }

        } catch (CommandFailedException $e) {
            $this->error([$e->getMessage()]);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Add a token
     */
    protected function add(): void
    {
        $this
            ->banner('Configuring new ' . static::LABEL . ' account')
            ->setVariables();

        if ($this->confirmVariables()) {
            $oAccount = new Account($this->sLabel, $this->sToken);
            static::addAccount($oAccount);
            $this->oOutput->writeln('');
            $this->oOutput->writeln('🎉 Saved credentials for <info>' . $oAccount->getLabel() . '</info>');
            $this->oOutput->writeln('');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Set the variables
     *
     * @return $this
     */
    protected function setVariables(): self
    {
        $this
            ->setLabel()
            ->setToken();

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the label property
     *
     * @return $this
     */
    protected function setLabel(): self
    {
        $sOption = trim($this->oInput->getOption('label') ?? '');
        if (empty($sOption)) {
            $this->sLabel = $this->ask(
                static::QUESTION_LABEL . ':',
                null,
                [$this, 'validateLabel']
            );
        } else {
            if ($this->validateLabel($sOption)) {
                $this->sLabel = $sOption;
            } else {
                $this->sLabel = $this->ask(
                    static::QUESTION_LABEL . ':',
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
    protected function setToken(): self
    {
        $sOption = trim($this->oInput->getOption('token') ?? '');
        if (empty($sOption)) {
            $this->sToken = $this->ask(
                static::QUESTION_TOKEN . ':',
                null,
                [$this, 'validateToken']
            );
        } else {
            if ($this->validateToken($sOption)) {
                $this->sToken = $sOption;
            } else {
                $this->sToken = $this->ask(
                    static::QUESTION_TOKEN . ':',
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
        if (empty($sLabel)) {
            $this->error(array_filter([
                'Label is required',
                $sLabel,
            ]));
            return false;
        }

        try {
            $oAccount = self::getAccountByLabel($sLabel);
            if (!empty($oAccount)) {
                $this->error([
                    'There is already an account called "' . $oAccount->getLabel() . '"',
                ]);
                return false;
            }
        } catch (AccountNotFoundException $e) {
        }

        $sPattern = '[^a-zA-Z0-9 \-]';

        if (preg_match('/' . $sPattern . '/', $sLabel)) {
            $this->error([
                '"' . $sLabel . '" contains invalid characters',
                $sPattern,
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
        if (empty($sToken)) {
            $this->error(array_filter([
                'Token is required',
                $sToken,
            ]));
            return false;
        }

        try {
            $oAccount = self::getAccountByToken($sToken);
            if (!empty($oAccount)) {
                $this->error([
                    'Token is already in use for account "' . $oAccount->getLabel() . '"',
                ]);
                return false;
            }
        } catch (AccountNotFoundException $e) {
        }

        try {
            $this->testToken($sToken);
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
    protected function confirmVariables()
    {
        $this->oOutput->writeln('');
        $this->oOutput->writeln('Does this all look OK?');
        $this->keyValueList([
            static::QUESTION_LABEL => $this->sLabel,
            static::QUESTION_TOKEN => $this->sToken,
        ]);
        return $this->confirm('Continue?');
    }

    // --------------------------------------------------------------------------

    /**
     * Return the key to use for the config
     *
     * @return string
     */
    protected static function getConfigKey(): string
    {
        return 'auth.accounts.' . static::SLUG;
    }

    // --------------------------------------------------------------------------

    /**
     * Return an array of saved accounts
     *
     * @return array
     */
    public static function getAccounts(): array
    {
        $aAccounts = (array) Config::get(static::getConfigKey()) ?: [];
        $aOut      = [];
        foreach ($aAccounts as $sLabel => $sToken) {
            $aOut[$sLabel] = new Account($sLabel, $sToken);
        }
        return $aOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Add an account
     *
     * @param Account $oAccount The account to add
     */
    public static function addAccount(Account $oAccount): void
    {
        try {

            static::getAccountByLabel($oAccount->getLabel());

        } catch (AccountNotFoundException $e) {

            $aConfig = [];
            foreach (static::getAccounts() as $oItem) {
                $aConfig[$oItem->getLabel()] = $oItem->getToken();
            }
            $aConfig[$oAccount->getLabel()] = $oAccount->getToken();

            ksort($aConfig);
            Config::set(static::getConfigKey(), $aConfig);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Delete an account
     *
     * @param Account $oAccount The account to delete
     */
    public static function deleteAccount(Account $oAccount): void
    {
        $aConfig = [];
        foreach (static::getAccounts() as $oItem) {
            if ($oAccount->getLabel() !== $oItem->getLabel() && $oAccount->getToken() !== $oItem->getToken()) {
                $aConfig[$oItem->getLabel()] = $oItem->getToken();
            }
        }

        Config::set(static::getConfigKey(), $aConfig);
    }

    // --------------------------------------------------------------------------

    /**
     * Get an account at a specific index
     *
     * @param int $iIndex The index to look for
     *
     * @return Account
     */
    public static function getAccountByIndex(int $iIndex): Account
    {
        $aAccounts = static::getAccounts();

        if (!array_key_exists($iIndex, $aAccounts)) {
            throw new AccountNotFoundException('No account at index "' . $iIndex . '"');
        }

        return $aAccounts[$iIndex];
    }

    // --------------------------------------------------------------------------

    /**
     * Get an account by it's label
     *
     * @param string $sLabel The label to search for
     *
     * @return Account
     */
    public static function getAccountByLabel(string $sLabel): Account
    {
        foreach (static::getAccounts() as $oAccount) {
            if ($sLabel === $oAccount->getLabel()) {
                return $oAccount;
            }
        }

        throw new AccountNotFoundException('No account with label "' . $sLabel . '"');
    }

    // --------------------------------------------------------------------------

    /**
     * Get an account by it's token
     *
     * @param string $sToken the token to search for
     *
     * @return Account
     */
    public static function getAccountByToken(string $sToken): Account
    {
        foreach (static::getAccounts() as $oAccount) {
            if ($sToken === $oAccount->getToken()) {
                return $oAccount;
            }
        }

        throw new AccountNotFoundException(
            'No account with ' . static::QUESTION_TOKEN . ' "' . $sToken . '"'
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Verify a token is valid
     *
     * @param string $sToken The token to validate
     */
    abstract protected function testToken(string $sToken): void;
}
