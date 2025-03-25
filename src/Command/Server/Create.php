<?php

namespace Shed\Cli\Command\Server;

use Exception;
use phpseclib3\Crypt\EC;
use phpseclib3\Net\SSH2;
use RuntimeException;
use Shed\Cli\Command;
use Shed\Cli\Entity\Provider\Account;
use Shed\Cli\Entity\Provider\Image;
use Shed\Cli\Entity\Provider\Region;
use Shed\Cli\Entity\Provider\Size;
use Shed\Cli\Entity\Server;
use Shed\Cli\Exceptions\Environment\NotValidException;
use Shed\Cli\Exceptions\Server\KeyNotGeneratedException;
use Shed\Cli\Exceptions\Server\TimeoutException;
use Shed\Cli\Helper\System;
use Shed\Cli\Interfaces\Provider;
use Shed\Cli\Service\ShedApi;
use Shed\Cli\Traits\Logging;
use stdClass;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;

final class Create extends Command
{
    use Logging;

    // --------------------------------------------------------------------------

    /**
     * The string for the production environment
     *
     * @var string
     */
    const ENV_PRODUCTION = 'PRODUCTION';

    /**
     * The string for the staging environment
     *
     * @var string
     */
    const ENV_STAGING = 'STAGING';

    /**
     * The available environments
     *
     * @var array
     */
    const ENVIRONMENTS = [
        self::ENV_PRODUCTION,
        self::ENV_STAGING,
    ];

    /**
     * The string value for Nails framework
     *
     * @var string
     */
    const FRAMEWORK_NAILS = 'NAILS';

    /**
     * The string value for Laravel framework
     *
     * @var string
     */
    const FRAMEWORK_LARAVEL = 'LARAVEL';

    /**
     * The string value for WordPress framework
     *
     * @var string
     */
    const FRAMEWORK_WORDPRESS = 'WORDPRESS';

    /**
     * The string value for static framework
     *
     * @var string
     */
    const FRAMEWORK_NONE = 'NONE';

    /**
     * The available frameworks
     *
     * @var array
     */
    //  @todo (Pablo - 2019-02-12) - Auto-detect supported backend environments
    const FRAMEWORKS = [
        self::FRAMEWORK_NAILS,
        self::FRAMEWORK_LARAVEL,
        self::FRAMEWORK_WORDPRESS,
        self::FRAMEWORK_NONE,
    ];

    /**
     * How long to wait for the SSH connection to be established
     *
     * @var int
     */
    const SSH_TIMEOUT = 120;

    /**
     * How long to wait for the SSL DNS to resolve
     *
     * @var int
     */
    const SSL_TIMEOUT = 300;

    // --------------------------------------------------------------------------

    /**
     * The domain name
     *
     * @var string
     */
    private $sDomain = '';

    /**
     * The environment being created
     *
     * @var int|string
     */
    private $sEnvironment = '';

    /**
     * The framework being used
     *
     * @var int|string
     */
    private $sFramework = '';

    /**
     * The provider being used
     *
     * @var string|Provider
     */
    private $oProvider = '';

    /**
     * The provider account to use
     *
     * @var Account
     */
    private $oAccount = null;

    /**
     * The provider region to use
     *
     * @var Region
     */
    private $oRegion = null;

    /**
     * The provider size to use
     *
     * @var Size
     */
    private $oSize = null;

    /**
     * The provider image to use
     *
     * @var Image
     */
    private $oImage = null;

    /**
     * The provider options
     *
     * @var array
     */
    private $aProviderOptions = [];

    /**
     * Keywords to give the server
     *
     * @var array
     */
    private $aKeywords = [];

    /**
     * An SSH key to assign to the deploy user
     *
     * @var string
     */
    private $sDeployKey = '';

    /**
     * The hostname to use
     *
     * @var string
     */
    private $sHostname = '';

    /**
     * The Shed Account to use
     *
     * @var Account
     */
    private $oShedAccount;

    /**
     * The Backup Account to use (for production)
     *
     * @var Account
     */
    private $oBackupAccount;

    /**
     * The database configuration
     *
     * @var stdClass
     */
    private $oDbConfig;

    /**
     * @var stdClass
     */
    private $oProvisionOutput;

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('server:create')
            ->setDescription('Create a new server')
            ->setHelp('This command will interactively create and configure a new server.')
            ->addOption(
                'domain',
                'd',
                InputOption::VALUE_OPTIONAL,
                'The domain name'
            )
            ->addOption(
                'hostname',
                'H',
                InputOption::VALUE_OPTIONAL,
                'The hostname'
            )
            ->addOption(
                'environment',
                'e',
                InputOption::VALUE_OPTIONAL,
                'The environment (one of: ' . implode(', ', self::ENVIRONMENTS) . ')'
            )
            ->addOption(
                'framework',
                'f',
                InputOption::VALUE_OPTIONAL,
                'The framework (one of: ' . implode(', ', self::FRAMEWORKS) . ')'
            )
            ->addOption(
                'provider',
                'p',
                InputOption::VALUE_OPTIONAL,
                'The provider to use'
            )
            ->addOption(
                'account',
                'a',
                InputOption::VALUE_OPTIONAL,
                'The provider account to use'
            )
            ->addOption(
                'region',
                'r',
                InputOption::VALUE_OPTIONAL,
                'The region to create the server in'
            )
            ->addOption(
                'size',
                's',
                InputOption::VALUE_OPTIONAL,
                'The size of server to create'
            )
            ->addOption(
                'image',
                'i',
                InputOption::VALUE_OPTIONAL,
                'The image to use'
            )
            ->addOption(
                'keywords',
                'k',
                InputOption::VALUE_OPTIONAL,
                'Any keywords to add to the server'
            )
            ->addOption(
                'deploy-key',
                'D',
                InputOption::VALUE_OPTIONAL,
                'An optional public key to assign the deploy user'
            )
            ->addOption(
                'ssh-wait',
                'w',
                InputOption::VALUE_REQUIRED,
                'Override how long the initial wait time for SSH to come online is'
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Execute the command
     *
     * @return int
     * @throws Exception
     */
    protected function go(): int
    {
        try {

            $this
                ->banner('Setting up a new server')
                ->checkEnvironment()
                ->setDomain()
                ->setHostname()
                ->setEnvironment()
                ->setFramework()
                ->setProvider()
                ->setAccount()
                ->setRegion()
                ->setSize()
                ->setImage()
                ->setProviderOptions()
                ->setKeywords()
                ->setDeployKey();

            if ($this->confirmVariables() && $this->confirmVpn()) {
                $this->createServer();
            }

            return self::EXIT_CODE_SUCCESS;

        } catch (\Throwable $e) {

            $this->error([
                'An error occurred whilst creating the server:',
                'Type:    ' . get_class($e),
                'Message: ' . $e->getMessage(),
                'File:    ' . $e->getFile(),
                'Line:    ' . $e->getLine(),
            ]);

            $this->oOutput->writeln('');
            return $this->confirm('Try again? [default: <info>no</info>]', false)
                ? $this->go()
                : self::EXIT_CODE_FAILURE;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Validates that the environment is usable
     *
     * @return $this
     * @throws NotValidException
     */
    private function checkEnvironment(): Create
    {
        $aRequiredFunctions = ['exec'];
        foreach ($aRequiredFunctions as $sRequiredFunction) {
            $this->logVerbose('Checking <info>' . $sRequiredFunction . '</info> exists... ');
            if (!function_exists($sRequiredFunction)) {
                throw new NotValidException('Missing function ' . $sRequiredFunction . '()');
            }
            $this->loglnVerbose('<info>exists</info>');
        }

        $aRequiredCommands = ['composer'];
        foreach ($aRequiredCommands as $sRequiredCommand) {
            $this->logVerbose('Checking <info>' . $sRequiredCommand . '</info> is installed... ');
            if (!System::commandExists($sRequiredCommand)) {
                throw new NotValidException($sRequiredCommand . ' is not installed');
            }
            $this->loglnVerbose('<info>installed</info>');
        }

        $this->logVerbose('Checking <info>shedcollective.com</info> accounts... ');
        $aShedAccounts = Command\Auth\Shed::getAccounts();
        if (empty($aShedAccounts)) {
            throw new NotValidException(
                'No shedcollective.com accounts available, add one use `auth:shed`'
            );
        } elseif (count($aShedAccounts) !== 1) {
            throw new NotValidException(
                'Only one shedcollective.com account permitted, ' . count($aShedAccounts) . ' detected'
            );
        }
        $this->loglnVerbose('<info>1 available</info>');

        $this->oShedAccount = reset($aShedAccounts);
        try {

            $this->logVerbose('Testing access token... ');
            ShedApi::testToken($this->oShedAccount->getToken(), $this->oOutput);
            $this->loglnVerbose('<info>valid</info>');

        } catch (Exception $e) {
            throw new NotValidException(
                'Access token for shedcollective.com account "' . $this->oShedAccount->getLabel() . '" is invalid: ' .
                $e->getMessage()
            );
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the domain property
     *
     * @return $this
     */
    private function setDomain(): Create
    {
        $this->loglnVeryVerbose('Setting domain');
        $sOption = strtolower(trim($this->oInput->getOption('domain') ?? $this->sDomain));
        if (empty($sOption) || !$this->validateDomain($sOption)) {
            $this->sDomain = $this->ask(
                'Domain Name:',
                null,
                [$this, 'validateDomain']
            );

        } else {
            $this->sDomain = $sOption;
        }

        $this->sDomain = $this->normaliseDomain($this->sDomain);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Normalises a domain
     *
     * @param string $sDomain The domain to normalise
     *
     * @return string
     */
    protected function normaliseDomain(string $sDomain): string
    {
        return strtolower(trim(rtrim($sDomain, '/')));
    }

    // --------------------------------------------------------------------------

    /**
     * Validate a domain is valid
     *
     * @param string $sDomain The domain to test
     *
     * @return bool
     */
    protected function validateDomain(string $sDomain): bool
    {
        $this->loglnVeryVerbose('Validating input: "' . $sDomain . '"');

        if (empty($sDomain)) {
            $this->error(array_filter([
                'Domain is required',
                $sDomain,
            ]));
            return false;
        }

        $sTestDomain = $sDomain;
        if (!preg_match('/^http/', $sTestDomain)) {
            $sTestDomain = 'https://' . $sTestDomain;
        }

        $aDomain = parse_url($sTestDomain);

        if (empty($aDomain) || !array_key_exists('host', $aDomain)) {
            $this->error(array_filter([
                'Invalid domain',
                $sDomain,
            ]));
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the environment property
     *
     * @return $this
     */
    private function setEnvironment(): Create
    {
        $this->loglnVeryVerbose('Setting environment');
        $sOption = trim($this->oInput->getOption('environment') ?? $this->sEnvironment);

        if (empty($sOption) || !$this->validateEnvironment($sOption)) {

            $this->sEnvironment = $this->choose(
                'Environment:',
                self::ENVIRONMENTS,
                null,
                [$this, 'validateEnvironment']
            );

        } elseif ($this->validateEnvironment($sOption)) {

            $this->sEnvironment = array_search($sOption, self::ENVIRONMENTS);
            $this->logln(
                sprintf(
                    '<comment>Environment</comment>: %s',
                    self::ENVIRONMENTS[$this->sEnvironment]
                )
            );
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Validate an environment is valid
     *
     * @param string $sEnvironment The environment to test
     *
     * @return bool
     */
    protected function validateEnvironment(string $sEnvironment): bool
    {
        $this->loglnVeryVerbose('Validating input: "' . $sEnvironment . '"');

        if (empty($sEnvironment)) {
            $this->error(array_filter([
                'Environment is required',
                $sEnvironment,
            ]));
            return false;
        }

        $sEnvironment = strtoupper($sEnvironment);
        if (!in_array($sEnvironment, self::ENVIRONMENTS)) {
            $this->error(array_filter([
                '"' . $sEnvironment . '" is not a valid Environment',
                'Should be one of: ' . implode(', ', self::ENVIRONMENTS),
            ]));
            return false;
        }

        if ($sEnvironment === self::ENV_PRODUCTION) {
            $aBackupAccounts = Command\Auth\Backup::getAccounts();
            if (empty($aBackupAccounts)) {
                throw new NotValidException(
                    'No backup accounts available, add one using `auth:backup`'
                );
            } elseif (count($aBackupAccounts) !== 1) {
                throw new NotValidException(
                    'Only one backup account permitted, ' . count($aBackupAccounts) . ' detected'
                );
            }
            $this->oBackupAccount = reset($aBackupAccounts);
        }

        return array_search($sEnvironment, self::ENVIRONMENTS) !== false;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the framework property
     *
     * @return $this
     */
    private function setFramework(): Create
    {
        $this->loglnVeryVerbose('Setting framework');
        $sOption = trim($this->oInput->getOption('framework') ?? $this->sFramework);

        if (empty($sOption) || !$this->validateFramework($sOption)) {

            $this->sFramework = $this->choose(
                'Framework:',
                self::FRAMEWORKS,
                null,
                [$this, 'validateFramework']
            );

        } else {

            $this->sFramework = array_search($sOption, self::FRAMEWORKS);
            $this->logln(
                sprintf(
                    '<comment>Framework</comment>: %s',
                    self::FRAMEWORKS[$this->sFramework]
                )
            );
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Validate a framework is valid
     *
     * @param string $sFramework The framework to test
     *
     * @return bool
     */
    protected function validateFramework(string $sFramework): bool
    {
        $this->loglnVeryVerbose('Validating input: "' . $sFramework . '"');

        if (empty($sFramework)) {
            $this->error(array_filter([
                'Framework is required',
                $sFramework,
            ]));
            return false;
        }

        $sFramework = strtoupper($sFramework);
        if (!in_array(strtoupper($sFramework), self::FRAMEWORKS)) {
            $this->error(array_filter([
                '"' . $sFramework . '" is not a valid Framework',
                'Should be one of: ' . implode(', ', self::FRAMEWORKS),
            ]));
            return false;
        }

        return array_search($sFramework, self::FRAMEWORKS) !== false;
    }

    // --------------------------------------------------------------------------

    /**
     * Looks up and sets the provider
     *
     * @return $this
     */
    private function setProvider(): Create
    {
        $this->loglnVeryVerbose('Setting provider');

        $aProviders           = [];
        $aProvidersNormalised = [];
        $aProviderClasses     = [];

        $sBasePath = BASEPATH . 'src';
        $oFinder   = new Finder();
        $oFinder->files()->in($sBasePath . '/Server/Provider/')->depth(0);
        foreach ($oFinder as $oFile) {

            $sClassName = $oFile->getBasename('.php');
            if ($sClassName === 'Command') {
                continue;
            }

            $sProvider = $oFile->getPath() . '/' . $sClassName;
            $sProvider = str_replace($sBasePath, 'Shed\Cli', $sProvider);
            $sProvider = str_replace('/', '\\', $sProvider);

            $oProvider = new $sProvider(
                $this->oInput,
                $this->oOutput
            );

            $sProviderName          = $oProvider->getLabel();
            $aProviders[]           = $sProviderName;
            $aProvidersNormalised[] = strtoupper($sProviderName);
            $aProviderClasses[]     = $oProvider;
        }

        if (count($aProviders) === 0) {
            throw new RuntimeException('No providers available');
        } elseif ($this->oInput->getOption('provider')) {

            $sOption = trim($this->oInput->getOption('provider') ?? '');
            $iChoice = array_search(strtoupper($sOption), $aProvidersNormalised);
            if ($iChoice === false) {
                $this->error([
                    '"' . $sOption . '" is not a valid provider option',
                ]);
                return $this->setProvider();
            }

            $this->logln('<comment>Provider</comment>: ' . $aProviderClasses[$iChoice]->getLabel());

        } elseif (count($aProviders) === 1) {
            $this->logln('Only one provider available: ' . $aProviders[0]);
            $iChoice = 0;
        } else {
            $iChoice = $this->choose('Provider', $aProviders);
        }

        $this->oProvider = $aProviderClasses[$iChoice];

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the account property
     *
     * @return $this
     */
    private function setAccount(): Create
    {
        $this->loglnVeryVerbose('Setting account');
        return $this->setProviderProperty(
            'Account',
            $this->oProvider->getAccounts(),
            $this->oInput->getOption('account'),
            $this->oAccount
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Set the region property
     *
     * @return $this
     */
    private function setRegion(): Create
    {
        $this->loglnVeryVerbose('Setting region');
        return $this->setProviderProperty(
            'Region',
            $this->oProvider->getRegions($this->oAccount),
            $this->oInput->getOption('region'),
            $this->oRegion
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Set the size property
     *
     * @return $this
     */
    private function setSize(): Create
    {
        $this->loglnVeryVerbose('Setting size');
        return $this->setProviderProperty(
            'Size',
            $this->oProvider->getSizes($this->oAccount),
            $this->oInput->getOption('size'),
            $this->oSize
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Set the image property
     *
     * @return $this
     */
    private function setImage(): Create
    {
        $this->loglnVeryVerbose('Setting image');
        return $this->setProviderProperty(
            'Image',
            $this->oProvider->getImages($this->oAccount),
            $this->oInput->getOption('image'),
            $this->oImage
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Sets a provider property
     *
     * @param string      $sLabel    The user-facing name of the property
     * @param array       $aOptions  The options to choose from
     * @param string|null $sDefault  The default value
     * @param mixed       $oProperty The property to assign the selected value to
     *
     * @return $this
     */
    private function setProviderProperty(
        string $sLabel,
        array $aOptions,
        ?string $sDefault,
        &$oProperty
    ): self {

        if (array_key_exists($sDefault, $aOptions)) {

            $oItem = $aOptions[$sDefault];
            $this->logln('<comment>' . $sLabel . '</comment>: ' . $oItem->getLabel());

        } elseif (count($aOptions) === 1) {

            $oItem = reset($aOptions);
            $this->logln('<comment>' . $sLabel . '</comment>: ' . $oItem->getLabel());

        } else {

            $iChoice = $this->choose(
                $sLabel . ':',
                array_values(array_map(function ($oItem) {

                    $sLabel = $oItem->getLabel();
                    $sLabel .= $oItem->getSlug() && $oItem->getSlug() !== $oItem->getLabel()
                        ? ' <info>(' . $oItem->getSlug() . ')</info>'
                        : '';

                    return $sLabel;
                }, $aOptions))
            );

            $oItem = array_values($aOptions)[$iChoice];
        }

        $oProperty = $oItem;

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Configures provider options
     *
     * @return $this
     */
    private function setProviderOptions(): Create
    {
        $this->loglnVeryVerbose('Setting provider options');

        $this->aProviderOptions = $this->oProvider->getOptions();

        foreach ($this->aProviderOptions as $oOption) {

            $sType       = $oOption->getType();
            $sLabel      = $oOption->getLabel();
            $aChoices    = $oOption->getOptions($this->aProviderOptions);
            $mDefault    = $oOption->getDefault();
            $mValidation = $oOption->getValidation();

            if ($sType === 'ask') {

                $mResult = $this->ask(
                    $sLabel,
                    $mDefault,
                    $mValidation
                );

            } elseif ($sType === 'choose' && !empty($aChoices)) {

                if (count($aChoices) === 1) {
                    reset($aChoices);
                    $mResult = key($aChoices);
                } else {
                    $mResult = $this->choose(
                        $sLabel,
                        $aChoices,
                        $mDefault,
                        $mValidation
                    );
                }

            } else {
                $mResult = null;
            }

            $oOption->setValue($mResult);
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the keywords property
     *
     * @return $this
     */
    private function setKeywords(): Create
    {
        $this->loglnVeryVerbose('Setting keywords');

        $sOption = trim($this->oInput->getOption('keywords') ?? implode(',', $this->aKeywords));
        if (empty($sOption)) {
            $sKeywords = $this->ask('Keywords:');
        } else {
            $sKeywords = $sOption;
            $this->logln('<comment>Keywords</comment>: ' . $sKeywords);
        }

        $aKeywords   = explode(',', $sKeywords);
        $aKeywords[] = self::ENVIRONMENTS[$this->sEnvironment];
        $aKeywords[] = self::FRAMEWORKS[$this->sFramework] !== self::FRAMEWORK_NONE
            ? self::FRAMEWORKS[$this->sFramework]
            : null;
        $aKeywords[] = $this->oImage->getLabel();
        $aKeywords   = array_values(
            array_filter(
                array_unique(
                    array_map(
                        function ($sKeyword) {

                            $sKeyword = strtolower((string) $sKeyword);
                            $sKeyword = preg_replace('/[^a-z0-9 \-]/', '', $sKeyword);
                            $sKeyword = str_replace(' ', '-', $sKeyword);
                            return trim($sKeyword);
                        },
                        $aKeywords
                    )
                )
            )
        );

        $this->aKeywords = $aKeywords;

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the deploy-key property
     *
     * @return $this
     */
    private function setDeployKey(): Create
    {
        $this->loglnVeryVerbose('Setting deploy key');

        $sOption = trim($this->oInput->getOption('deploy-key') ?? $this->sDeployKey);
        if (empty($sOption)) {
            $this->sDeployKey = $this->ask('Deploy Key:');
        } else {
            $this->sDeployKey = $sOption;
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the hostname to use, or generates one if not provided
     *
     * @return Create
     */
    private function setHostname(): Create
    {
        $this->loglnVeryVerbose('Setting hostname');

        $sOption = strtolower(trim($this->oInput->getOption('hostname') ?? $this->sHostname));
        if (empty($sOption)) {
            $sOption = implode(
                '-',
                array_map(
                    function ($sBit) {
                        return preg_replace(
                            '/[^a-z0-9\-]/',
                            '',
                            str_replace('.', '-', strtolower((string) $sBit))
                        );
                    },
                    array_filter([
                        $this->sDomain,
                    ])
                )
            );
        }

        $this->sHostname = $this->ask(
            'Hostname:',
            $sOption,
            [$this, 'validateHostname']
        );

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Validate a hostname is valid
     *
     * @param string $sHostname The domain to test
     *
     * @return bool
     */
    protected function validateHostname(string $sHostname): bool
    {
        $this->loglnVeryVerbose('Validating input: "' . $sHostname . '"');

        if (empty($sHostname)) {
            $this->error(array_filter([
                'Hostname is required',
                $sHostname,
            ]));
            return false;
        }

        if (preg_match('/[^a-z\-0-9]/', $sHostname)) {
            $this->error(array_filter([
                'Invalid hostname (a-z, 0-9, and dashes only)',
                $sHostname,
            ]));
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
    private function confirmVariables(): bool
    {
        $this->logln('');
        $this->logln('A new server will be provisioned with the following details:');

        $aKeyValues = [
            'Domain'      => $this->sDomain,
            'Hostname'    => $this->sHostname,
            'Environment' => self::ENVIRONMENTS[$this->sEnvironment],
            'Framework'   => self::FRAMEWORKS[$this->sFramework],
            'Provider'    => $this->oProvider->getLabel(),
            'Account'     => $this->oAccount->getLabel(),
            'Region'      => $this->oRegion->getLabel(),
            'Size'        => $this->oSize->getLabel(),
            'Image'       => $this->oImage->getLabel(),
        ];

        foreach ($this->aProviderOptions as $oOption) {
            $oSummary                     = $oOption->summarise($this->aProviderOptions);
            $aKeyValues[$oSummary->label] = $oSummary->summary;
        }

        $aKeyValues['Keywords']   = implode(', ', $this->aKeywords);
        $aKeyValues['Deploy Key'] = $this->sDeployKey ? 'Set' : 'None';

        $this->keyValueList($aKeyValues);
        return $this->confirm('Continue? [default: <info>yes</info>]');
    }

    // --------------------------------------------------------------------------

    /**
     * Confirms the user is on their VPN
     *
     * @return bool
     */
    private function confirmVpn(): bool
    {
        return $this->confirm('VPN required. Is it connected? [default: <info>yes</info>]');
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new project
     *
     * @return $this
     * @throws Exception
     */
    private function createServer(): Create
    {
        $bEnableBackups = self::ENVIRONMENTS[$this->sEnvironment] === self::ENV_PRODUCTION;

        $this->logln('');

        // --------------------------------------------------------------------------

        $this->log('Generating temporary SSH key... ');
        $oPrivateKey = $this->generateSshKey();
        $this->logln('<info>' . $oPrivateKey->getPublicKey()->getFingerprint() . '</info>');

        // --------------------------------------------------------------------------

        $this->log('Creating server... ');

        //  @todo (Pablo - 2019-08-02) - Register with Shed API, but in a pending state

        $oServer = $this->oProvider->create(
            $this->sDomain,
            $this->sHostname,
            self::ENVIRONMENTS[$this->sEnvironment],
            self::FRAMEWORKS[$this->sFramework],
            $this->oAccount,
            $this->oRegion,
            $this->oSize,
            $this->oImage,
            $this->aProviderOptions,
            $this->aKeywords,
            $this->sDeployKey,
            $oPrivateKey
        );
        $this->logln('<info>done</info>');
        $this->logln('Server IP is <info>' . $oServer->getIp() . '</info>');

        // --------------------------------------------------------------------------

        $oSsh = $this->waitForSsh($oServer, $oPrivateKey);

        // --------------------------------------------------------------------------

        $this
            ->disableRootLogin($oSsh)
            ->randomiseRootPassword($oSsh)
            ->setDomainEnvVar($oSsh)
            ->configureHostname($oSsh)
            ->addDeployKey($oSsh)
            ->configureMySQL($oSsh)
            ->secureMySQL($oSsh)
            ->configureBackups($oSsh, $bEnableBackups)
            ->configureSsl($oSsh, $oServer)
            ->updateDependencies($oSsh)
            ->provisionFramework($oSsh)
            ->reboot($oSsh);

        // --------------------------------------------------------------------------

        try {
            //  @todo (Pablo - 2019-08-02) - Update server state with Shed API
            $this->log('Registering with the Shed API... ');
            ShedApi::createServer($this->oShedAccount, $oServer);
            $this->logln('<info>done</info>');
        } catch (Exception $e) {
            $this->warning(array_filter([
                'Failed to register server with the Shed API',
                $e->getMessage(),
            ]));
        }

        // --------------------------------------------------------------------------

        $this->keyValueList(
            [
                'ID'         => $oServer->getId(),
                'IP Address' => $oServer->getIp(),
                'Domain'     => $oServer->getDomain(),
                'Disk'       => $oServer->getDisk()->getLabel(),
                'Image'      => $oServer->getImage()->getLabel(),
                'Region'     => $oServer->getRegion()->getLabel(),
                'Size'       => $oServer->getSize()->getLabel(),
            ],
            'Server Details'
        );

        if ($this->shouldConfigureMySQL()) {
            if (!empty($this->oDbConfig->error)) {
                $this->warning(
                    array_filter(
                        array_merge(
                            ['There was an error configuring MySQL:'],
                            (array) $this->oDbConfig->error,
                            $bEnableBackups
                                ? ['Additionally, you will have to configure backups manually']
                                : [null]
                        )
                    )
                );
            } else {
                $this->keyValueList(
                    [
                        'MySQL host'      => '127.0.0.1 (over SSH)',
                        'MySQL user'      => $this->oDbConfig->user,
                        'MySQL pass'      => $this->oDbConfig->password,
                        'MySQL databases' => implode(', ', $this->oDbConfig->databases),
                    ],
                    'MySQL Details'
                );
            }
        }

        if ($this->oProvisionOutput) {
            $this->keyValueList(
                (array) $this->oProvisionOutput,
                'Framework'
            );
        }

        // --------------------------------------------------------------------------

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a temporary RSA key
     *
     * @return EC\PrivateKey
     */
    private function generateSshKey(): EC\PrivateKey
    {
        $sKeyPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid();

        $this->loglnVerbose('Key path: ' . $sKeyPath);

        $sCommand = sprintf(
            'ssh-keygen -q -b 4096 -C "generated-ssh-key" -f "%s" -N "" -t ed25519',
            $sKeyPath
        );

        $this->loglnVeryVerbose('Executing `' . $sCommand . '`');

        exec(
            $sCommand,
            $aOutput,
            $iExitCode
        );

        $this->loglnVeryVerbose('Exit code: ' . $iExitCode);

        if ($iExitCode || !file_exists($sKeyPath)) {
            throw new KeyNotGeneratedException(
                sprintf(
                    'Failed to generate SSH key (exit code: %s)',
                    $iExitCode
                )
            );
        }

        $this->loglnVeryVerbose('Loading key into memory');

        /** @var EC\PrivateKey $oKey */
        $oKey = EC::load(file_get_contents($sKeyPath));

        $this->loglnVeryVerbose('Deleting key files');
        unlink($sKeyPath);
        unlink($sKeyPath . '.pub');

        return $oKey;
    }

    // --------------------------------------------------------------------------

    /**
     * Waits for an SSH connection to be established
     *
     * @param Server        $oServer The server to connect to
     * @param EC\PrivateKey $oKey    The key to use
     *
     * @return SSH2
     */
    private function waitForSsh(Server $oServer, EC\PrivateKey $oKey): SSH2
    {
        $this->log('Waiting for SSH access... ');
        $iStart = time();

        //  Give the OS some time to start sshd
        $iIntervalWait = 10;
        $iProviderWait = (int) $this->oInput->getOption('ssh-wait') ?: $this->oProvider->getSshInitialWait();
        $iInitialWait  = $iProviderWait - $iIntervalWait;

        if ($iInitialWait < $iIntervalWait) {
            $iInitialWait = $iIntervalWait;
        }

        sleep($iInitialWait);

        $iPreviousErrorReporting = error_reporting(0);

        do {

            sleep($iIntervalWait);

            if (time() - $iStart >= self::SSH_TIMEOUT) {
                throw new TimeoutException(
                    sprintf(
                        'Timed out waiting for server to allow SSH access (timeout: %s seconds)',
                        self::SSH_TIMEOUT
                    )
                );
            } else {
                $this->logVerbose('Attempting connection... ');
                $oSsh       = new SSH2($oServer->getIp());
                $bConnected = $oSsh->login('root', $oKey);
                if (!$bConnected) {
                    $this->loglnVerbose('not connected');
                }
            }

        } while (!$bConnected);

        error_reporting($iPreviousErrorReporting);

        $this->logln('<info>connected</info>');

        return $oSsh;
    }

    // --------------------------------------------------------------------------

    /**
     * Disables the root login
     *
     * @param SSH2 $oSsh The SSH connection
     *
     * @return $this
     */
    private function disableRootLogin(SSH2 $oSsh): self
    {
        $this->log('Disabling root login... ');
        $oSsh->exec('rm -f /root/.ssh/authorized_keys');
        $oSsh->exec('echo \'PermitRootLogin no\' >> /etc/ssh/sshd_config');
        $oSsh->exec('service ssh restart');
        $this->logln('<info>done</info>');
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Randomise root password
     *
     * @param SSH2 $oSsh The SSH connection
     *
     * @return $this
     */
    private function randomiseRootPassword(SSH2 $oSsh): self
    {
        $this->log('Randomising root password... ');
        $oSsh->exec('usermod --password $(openssl rand -base64 32) root');
        $this->logln('<info>done</info>');
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the domain env var
     *
     * @param SSH2 $oSsh The SSH connection
     *
     * @return $this
     */
    private function setDomainEnvVar(SSH2 $oSsh): self
    {
        $this->log('Setting domain as env var... ');
        $oSsh->exec('sed -E -i \'s/DOMAIN="localhost"/DOMAIN="' . $this->sDomain . '"/g\' /home/deploy/.env.sh');
        $this->logln('<info>done</info>');

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the system's hostname
     *
     * @param SSH2 $oSsh The SSH connection
     *
     * @return $this
     */
    private function configureHostname(SSH2 $oSsh): self
    {
        $this->log('Setting hostname... ');
        $oSsh->exec('hostname ' . $this->sHostname);
        $oSsh->exec('sed -Ei "s:127\.0\.1\.1.+:127.0.1.1 ' . $this->sHostname . ':g" /etc/hosts');
        $oSsh->exec('echo "' . $this->sHostname . '" > /etc/hostname');
        $this->logln('<info>done</info>');

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Adds the deploy key
     *
     * @param SSH2 $oSsh The SSH connection
     *
     * @return $this
     */
    private function addDeployKey(SSH2 $oSsh): self
    {
        if ($this->sDeployKey) {
            $this->log('Adding deploy key... ');
            $oSsh->exec('echo "' . $this->sDeployKey . '" >> /home/deploy/.ssh/authorized_keys');
            $this->logln('<info>done</info>');
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Whether MySQL needs to be configured
     *
     * @return bool
     */
    private function shouldConfigureMySQL(): bool
    {
        return preg_match('/(mysql(57|80)|lamp)/', $this->oImage->getLabel());
    }

    // --------------------------------------------------------------------------

    /**
     * Configures the database
     *
     * @param SSH2 $oSsh The SSH connection
     *
     * @return $this
     * @throws Exception
     */
    private function configureMySQL(SSH2 $oSsh): self
    {
        if (!$this->shouldConfigureMySQL()) {
            return $this;
        }

        $this->log('Configuring database... ');

        $sConfig = $oSsh->exec(
            sprintf(
                '/root/mysql-setup-db.sh %s %s',
                strtolower(self::ENVIRONMENTS[$this->sEnvironment]),
                strtolower(
                    sprintf(
                        '%s_%s',
                        str_replace('.', '_', preg_replace('/[^a-zA-Z0-9.]/', '', $this->sDomain)),
                        self::ENVIRONMENTS[$this->sEnvironment]
                    )
                )
            )
        );

        $this->oDbConfig = json_decode($sConfig);

        if (empty($this->oDbConfig)) {
            $this->oDbConfig = (object) [
                'error' => [
                    'Failed to decode config. ',
                    json_last_error_msg(),
                    $sConfig,
                ],
            ];
        }

        if (!empty($this->oDbConfig->error)) {
            $sError = is_array($this->oDbConfig->error) ? implode(' ', $this->oDbConfig->error) : $this->oDbConfig->error;
            $this->logln('<error>' . $sError . '</error>');
        } else {
            $this->logln('<info>done</info>');
        }

        $oSsh->exec('rm -f /root/mysql-setup-db.sh');

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Secures MySQL
     *
     * @param SSH2 $oSsh The SSH connection
     *
     * @return $this
     * @throws Exception
     */
    private function secureMySQL(SSH2 $oSsh): self
    {
        if (!$this->shouldConfigureMySQL()) {
            return $this;
        }

        $this->log('Securing MySQL... ');
        $oSsh->exec('echo $(openssl rand -base64 32) > /root/.mysql_root_password');
        $oSsh->exec('$MYSQL_ROOT_PW = $(cat /root/.mysql_root_password) &&  mysql_secure_installation --use-default -p${MYSQL_ROOT_PW}');
        $this->logln('<info>done</info>');

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Configures backups
     *
     * @param SSH2 $oSsh           The SSH connection
     * @param bool $bEnableBackups Whether to enable backups or not
     *
     * @return $this
     */
    private function configureBackups(SSH2 $oSsh, bool $bEnableBackups): self
    {
        if ($bEnableBackups) {

            $this->log('Configuring backups... ');

            $oSsh->exec('echo \'export DOMAIN="' . $this->sDomain . '"\' >> /root/.backupconfig');
            $oSsh->exec('echo \'export S3_ACCESS_KEY="' . $this->oBackupAccount->getLabel() . '"\' >> /root/.backupconfig');
            $oSsh->exec('echo \'export S3_ACCESS_SECRET="' . $this->oBackupAccount->getToken() . '"\' >> /root/.backupconfig');
            $oSsh->exec('echo \'export S3_BUCKET="shed-backups"\' >> /root/.backupconfig');

            //  Database backups
            if ($this->shouldConfigureMySQL() && empty($this->oDbConfig->error)) {
                $oSsh->exec('echo \'export MYSQL_HOST="127.0.0.1"\' >> /root/.backupconfig');
                $oSsh->exec('echo \'export MYSQL_USER="' . $this->oDbConfig->user . '"\' >> /root/.backupconfig');
                $oSsh->exec('echo \'export MYSQL_PASSWORD="' . $this->oDbConfig->password . '"\' >> /root/.backupconfig');
                $oSsh->exec('echo \'export MYSQL_DATABASE="' . reset($this->oDbConfig->databases) . '"\' >> /root/.backupconfig');
            }

            //  Directory backups
            $oSsh->exec('echo \'export DIRECTORY="/home/deploy/www"\' >> /root/.backupconfig');

            $this->logln('<info>done</info>');
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Whether SSL needs to be configured
     *
     * @return bool
     */
    private function shouldConfigureSSL(): bool
    {
        return !preg_match('/^docker-/', $this->oImage->getLabel());
    }

    // --------------------------------------------------------------------------

    /**
     * configures SSL
     *
     * @param SSH2   $oSsh    The SSH connection
     * @param Server $oServer The server which is being configured
     *
     * @return $this
     */
    private function configureSsl(SSH2 $oSsh, Server $oServer): self
    {
        if (!$this->shouldConfigureSSL()) {
            return $this;
        }

        $this->logln('');
        if ($this->confirm('Would you like to configure an SSL certificate for this server? [default: <info>yes</info>]')) {

            $this->logln('');
            $this->logln('Ensure DNS records have been deployed for:');
            $this->logln('- A <info>' . $oServer->getIp() . '</info> ' . $this->sDomain);
            $this->logln('- A <info>' . $oServer->getIp() . '</info> www.' . $this->sDomain . ' <comment>(optional)</comment>');
            $this->logln('');

            $this->log('Waiting for DNS to propagate... ');
            $iStart = time();

            do {

                sleep(10);

                if (time() - $iStart >= self::SSL_TIMEOUT) {

                    $this->logln('<error>timeout</error>');
                    $this->warning([
                        'Timed out waiting for DNS to propagate (timeout: ' . self::SSL_TIMEOUT . ' seconds)',
                        'You will need to manually configure SSL: SSH in as root and execute `ssl-create`',
                    ]);
                    $bResolved = true;

                } else {

                    $aRecords  = array_filter((array) dns_get_record($this->sDomain, DNS_A));
                    $aRecord   = reset($aRecords);
                    $bResolved = !empty($aRecord['ip']) && $aRecord['ip'] == $oServer->getIp();

                    if ($bResolved) {

                        $this->logln('<info>done</info>');
                        $this->log('Generating certificates... ');
                        $oSsh->exec('ssl-create');
                        $this->logln('<info>done</info>');
                        $this->log('Restarting Apache... ');
                        $oSsh->exec('service apache2 restart');
                        $this->logln('<info>done</info>');
                    }
                }

            } while (empty($bResolved));
        } else {
            $this->warning([
                'Execute `ssl-create` as root when you are ready to deploy SSL certificates for this server.',
            ]);
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Brings dependencies up to date
     *
     * @param SSH2 $oSsh The SSH connection
     *
     * @return $this
     */
    private function updateDependencies(SSH2 $oSsh): self
    {
        $this->log('Updating dependencies (this may take some time)... ');
        $oSsh->exec('apt update -y');
        $oSsh->exec('export DEBIAN_FRONTEND=noninteractive');
        $oSsh->exec('apt upgrade -y');
        $oSsh->exec('apt autoremove -y');
        $oSsh->exec('apt autoclean -y');
        $this->logln('<info>done</info>');

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Executes any post-provision scripts placed by the provisioner
     *
     * @param SSH2 $oSsh
     *
     * @return $this
     * @throws Exception
     */
    private function provisionFramework(SSH2 $oSsh): self
    {
        $sFile      = '/root/install-framework.sh';
        $aDatabases = $this->oDbConfig->databases ?? [];
        $sCommand   = implode(' ', [
            $sFile,
            $this->oDbConfig->host ?? 'localhost',
            $this->oDbConfig->user ?? '',
            $this->oDbConfig->password ?? '',
            reset($aDatabases) ?: '',
            $this->sDomain,
        ]);
        $sCommand   = sprintf(
            'if [[ -f %1$s ]]; then %2$s && rm -f %1$s; fi',
            $sFile,
            $sCommand
        );

        $this->log('Running post-install scripts... ');
        $sOutput = $oSsh->exec($sCommand);

        if (!empty($sOutput)) {
            $this->oProvisionOutput = json_decode($sOutput);
            if (json_last_error()) {
                $this->logln('<error>ERROR</error>');
                $this->logln('<error>Failed to decode output of provisioning script</error>');
                $this->logln('<error>Output: ' . $sOutput . '</error>');
            } else {
                $this->logln('<info>done</info>');
            }
        } else {
            $this->logln('<info>done</info>');
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * @param SSH2 $oSsh The SSH connection
     *
     * @return $this
     */
    private function reboot(SSH2 &$oSsh): self
    {
        $this->logln('Rebooting server... ');
        $oSsh->exec('reboot');

        return $this;
    }
}
