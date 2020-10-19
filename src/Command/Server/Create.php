<?php

namespace Shed\Cli\Command\Server;

use Exception;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SSH2;
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
use stdClass;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;

final class Create extends Command
{
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
    const FRAMEWORK_STATIC = 'STATIC';

    /**
     * The available frameworks
     *
     * @var array
     * @todo (Pablo - 2019-02-12) - Auto-detect supported backend environments
     */
    const FRAMEWORKS = [
        self::FRAMEWORK_NAILS,
        self::FRAMEWORK_LARAVEL,
        self::FRAMEWORK_WORDPRESS,
        self::FRAMEWORK_STATIC,
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
     * @var string
     */
    private $sEnvironment = '';

    /**
     * The framework being used
     *
     * @var string
     */
    private $sFramework = '';

    /**
     * The provider being used
     *
     * @var Provider
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
                'environment',
                'e',
                InputOption::VALUE_OPTIONAL,
                'The environment (one of: ' . implode(', ', static::ENVIRONMENTS) . ')'
            )
            ->addOption(
                'framework',
                'f',
                InputOption::VALUE_OPTIONAL,
                'The framework (one of: ' . implode(', ', static::ENVIRONMENTS) . ')'
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
        $this
            ->banner('Setting up a new server')
            ->checkEnvironment()
            ->setDomain()
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

        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    /**
     * Validates that the environment is usable
     *
     * @return $this
     * @throws NotValidException
     *
     */
    private function checkEnvironment(): Create
    {
        if (!function_exists('exec')) {
            throw new NotValidException('Missing function exec()');
        }

        $aRequiredCommands = ['composer'];
        foreach ($aRequiredCommands as $sRequiredCommand) {
            if (!System::commandExists($sRequiredCommand)) {
                throw new NotValidException($sRequiredCommand . ' is not installed');
            }
        }

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
        $this->oShedAccount = reset($aShedAccounts);
        try {

            ShedApi::testToken($this->oShedAccount->getToken());

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
        $sOption = trim($this->oInput->getOption('domain'));
        if (empty($sOption)) {
            $this->sDomain = $this->ask(
                'Domain Name:',
                null,
                [$this, 'validateDomain']
            );
        } else {
            if ($this->validateDomain($sOption)) {
                $this->sDomain = $sOption;
                $this->oOutput->writeln('<comment>Domain Name</comment>: ' . $this->sDomain);
            } else {
                $this->sDomain = $this->ask(
                    'Domain Name:',
                    null,
                    [$this, 'validateDomain']
                );
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Validate a domain is valid
     *
     * @param string $sDomain The domain to test
     *
     * @return bool
     */
    protected function validateDomain($sDomain): bool
    {
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
        $sOption = trim($this->oInput->getOption('environment'));

        if (empty($sOption) || !$this->validateEnvironment($sOption)) {

            $this->sEnvironment = $this->choose(
                'Environment:',
                static::ENVIRONMENTS,
                null,
                [$this, 'validateEnvironment']
            );

        } elseif ($this->validateEnvironment($sOption)) {

            $this->sEnvironment = array_search($sOption, static::ENVIRONMENTS);
            $this->oOutput->writeln(
                sprintf(
                    '<comment>Environment</comment>: %s',
                    static::ENVIRONMENTS[$this->sEnvironment]
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
    protected function validateEnvironment($sEnvironment): bool
    {
        if (empty($sEnvironment)) {
            $this->error(array_filter([
                'Environment is required',
                $sEnvironment,
            ]));
            return false;
        }

        $sEnvironment = strtoupper($sEnvironment);
        if (!in_array($sEnvironment, static::ENVIRONMENTS)) {
            $this->error(array_filter([
                '"' . $sEnvironment . '" is not a valid Environment',
                'Should be one of: ' . implode(', ', static::ENVIRONMENTS),
            ]));
            return false;
        }

        if ($sEnvironment === static::ENV_PRODUCTION) {
            $aBackupAccounts = Command\Auth\Backup::getAccounts();
            if (empty($aBackupAccounts)) {
                throw new NotValidException(
                    'No backup accounts available, add one use `auth:backup`'
                );
            } elseif (count($aBackupAccounts) !== 1) {
                throw new NotValidException(
                    'Only one backup account permitted, ' . count($aBackupAccounts) . ' detected'
                );
            }
            $this->oBackupAccount = reset($aBackupAccounts);
        }

        return array_search($sEnvironment, static::ENVIRONMENTS) !== false;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the framework property
     *
     * @return $this
     */
    private function setFramework(): Create
    {
        $sOption = trim($this->oInput->getOption('framework'));

        if (empty($sOption) || !$this->validateFramework($sOption)) {

            $this->sFramework = $this->choose(
                'Framework:',
                static::FRAMEWORKS,
                null,
                [$this, 'validateFramework']
            );

        } else {

            $this->sFramework = array_search($sOption, static::FRAMEWORKS);
            $this->oOutput->writeln(
                sprintf(
                    '<comment>Framework</comment>: %s',
                    static::FRAMEWORKS[$this->sFramework]
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
    protected function validateFramework($sFramework): bool
    {
        if (empty($sFramework)) {
            $this->error(array_filter([
                'Framework is required',
                $sFramework,
            ]));
            return false;
        }

        $sFramework = strtoupper($sFramework);
        if (!in_array(strtoupper($sFramework), static::FRAMEWORKS)) {
            $this->error(array_filter([
                '"' . $sFramework . '" is not a valid Framework',
                'Should be one of: ' . implode(', ', static::FRAMEWORKS),
            ]));
            return false;
        }

        return array_search($sFramework, static::FRAMEWORKS) !== false;
    }

    // --------------------------------------------------------------------------

    /**
     * Looks up and sets the provider
     *
     * @return $this
     */
    private function setProvider(): Create
    {
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
            throw new \RuntimeException('No providers available');
        } elseif ($this->oInput->getOption('provider')) {

            $sOption = trim($this->oInput->getOption('provider'));
            $iChoice = array_search(strtoupper($sOption), $aProvidersNormalised);
            if ($iChoice === false) {
                $this->error([
                    '"' . $sOption . '" is not a valid provider option',
                ]);
                return $this->setProvider();
            }

            $this->oOutput->writeln('<comment>Provider</comment>: ' . $aProviderClasses[$iChoice]->getLabel());

        } elseif (count($aProviders) === 1) {
            $this->oOutput->writeln('Only one provider available: ' . $aProviders[0]);
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
     * @param string $sLabel    The user-facing name of the property
     * @param array  $aOptions  The options to choose from
     * @param string $sDefault  The default value
     * @param mixed  $oProperty The property to assign the selected value to
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
            $this->oOutput->writeln('<comment>' . $sLabel . '</comment>: ' . $oItem->getLabel());

        } elseif (count($aOptions) === 1) {

            $oItem = reset($aOptions);
            $this->oOutput->writeln('<comment>' . $sLabel . '</comment>: ' . $oItem->getLabel());

        } else {

            $iChoice = $this->choose(
                $sLabel . ':',
                array_values(array_map(function ($oItem) {
                    $sLabel = $oItem->getLabel();
                    $sLabel .= $oItem->getSlug() && $oItem->getSlug() !== $oItem->getLabel() ? ' <info>(' . $oItem->getSlug() . ')</info>' : '';
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
        $this->aProviderOptions = $this->oProvider->getOptions();

        foreach ($this->aProviderOptions as $sKey => $oOption) {

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
        $sOption = trim($this->oInput->getOption('keywords'));
        if (empty($sOption)) {
            $sKeywords = $this->ask('Keywords:');
        } else {
            $sKeywords = $sOption;
            $this->oOutput->writeln('<comment>Keywords</comment>: ' . $sKeywords);
        }

        $aKeywords   = explode(',', $sKeywords);
        $aKeywords[] = static::ENVIRONMENTS[$this->sEnvironment];
        $aKeywords[] = static::FRAMEWORKS[$this->sFramework];
        $aKeywords[] = $this->oImage->getLabel();
        $aKeywords   = array_values(
            array_filter(
                array_unique(
                    array_map(
                        function ($sKeyword) {
                            $sKeyword = strtolower($sKeyword);
                            $sKeyword = preg_replace('/[^a-z0-9 \-]/', '', $sKeyword);
                            $sKeyword = str_replace(' ', '-', $sKeyword);
                            $sKeyword = trim($sKeyword);
                            return $sKeyword;
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
        $sOption = trim($this->oInput->getOption('deploy-key'));
        if (empty($sOption)) {
            $this->sDeployKey = $this->ask('Deploy Key:');
        } else {
            $this->sDeployKey = $sOption;
        }

        return $this;
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
        $this->oOutput->writeln('A new server will be provisioned with the following details:');

        $aKeyValues = [
            'Domain'      => $this->sDomain,
            'Environment' => static::ENVIRONMENTS[$this->sEnvironment],
            'Framework'   => static::FRAMEWORKS[$this->sFramework],
            'Provider'    => $this->oProvider->getLabel(),
            'Account'     => $this->oAccount->getLabel(),
            'Region'      => $this->oRegion->getLabel(),
            'Size'        => $this->oSize->getLabel(),
            'Image'       => $this->oImage->getLabel(),
        ];

        foreach ($this->aProviderOptions as $sKey => $oOption) {
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
    private function confirmVpn()
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
        $bEnableBackups = static::ENVIRONMENTS[$this->sEnvironment] === static::ENV_PRODUCTION;

        $this->oOutput->writeln('');

        // --------------------------------------------------------------------------

        $this->oOutput->write('Generating temporary SSH key... ');
        $oKey = $this->generateSshKey();
        $this->oOutput->writeln('<info>' . $oKey->getPublicKeyFingerprint() . '</info>');

        // --------------------------------------------------------------------------

        $this->oOutput->write('Creating server... ');

        //  @todo (Pablo - 2019-08-02) - Register with Shed API, but in a pending state

        $oServer = $this->oProvider->create(
            $this->sDomain,
            static::ENVIRONMENTS[$this->sEnvironment],
            static::FRAMEWORKS[$this->sFramework],
            $this->oAccount,
            $this->oRegion,
            $this->oSize,
            $this->oImage,
            $this->aProviderOptions,
            $this->aKeywords,
            $this->sDeployKey,
            $oKey
        );
        $this->oOutput->writeln('<info>done</info>');
        $this->oOutput->writeln('Server IP is <info>' . $oServer->getIp() . '</info>');

        // --------------------------------------------------------------------------

        $oSsh = $this->waitForSsh($oServer, $oKey);

        // --------------------------------------------------------------------------

        $this
            ->disableRootLogin($oSsh)
            ->randomiseRootPassword($oSsh)
            ->setDomainEnvVar($oSsh)
            ->setHostname($oSsh)
            ->addDeployKey($oSsh)
            ->configureMySQL($oSsh)
            ->secureMySQL($oSsh)
            ->configureBackups($oSsh, $bEnableBackups)
            ->configureSsl($oSsh, $oServer)
            ->provisionFramework($oSsh);

        // --------------------------------------------------------------------------

        try {
            //  @todo (Pablo - 2019-08-02) - Update server state with Shed API
            $this->oOutput->write('Registering with the Shed API... ');
            ShedApi::createServer($this->oShedAccount, $oServer);
            $this->oOutput->writeln('<info>done</info>');
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
     * @return RSA
     */
    private function generateSshKey(): RSA
    {
        $sKeyPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid();

        exec(
            sprintf(
                'ssh-keygen -q -b 4096 -C "generated-ssh-key" -f "%s" -N "" -t rsa',
                $sKeyPath
            ),
            $aOutput,
            $iExitCode
        );

        if ($iExitCode || !file_exists($sKeyPath)) {
            throw new KeyNotGeneratedException(
                sprintf(
                    'Failed to generate SSH key (exit code: %s)',
                    $iExitCode
                )
            );
        }

        $oKey = new RSA();
        $oKey->loadKey(file_get_contents($sKeyPath));

        unlink($sKeyPath);
        unlink($sKeyPath . '.pub');

        return $oKey;
    }

    // --------------------------------------------------------------------------

    /**
     * Waits for an SSH connection to be established
     *
     * @param Server $oServer The server to connect to
     * @param RSA    $oKey    The key to use
     *
     * @return SSH2
     */
    private function waitForSsh(Server $oServer, RSA $oKey): SSH2
    {
        $this->oOutput->write('Waiting for SSH access... ');
        $iStart = time();

        //  Make initial sleep 20 seconds to give the Os some time to start sshd
        sleep(10);

        $iPreviousErrorReporting = error_reporting(0);

        do {

            sleep(10);

            if (time() - $iStart >= static::SSH_TIMEOUT) {
                throw new TimeoutException(
                    sprintf(
                        'Timed out waiting for server to allow SSH access (timeout: %s seconds)',
                        static::SSH_TIMEOUT
                    )
                );
            } else {
                $oSsh       = new SSH2($oServer->getIp());
                $bConnected = $oSsh->login('root', $oKey);
            }

        } while (!$bConnected);

        error_reporting($iPreviousErrorReporting);

        $this->oOutput->writeln('<info>done</info>');

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
        $this->oOutput->write('Disabling root login... ');
        $oSsh->exec('rm -f /root/.ssh/authorized_keys');
        $oSsh->exec('echo \'PermitRootLogin no\' >> /etc/ssh/sshd_config');
        $oSsh->exec('service ssh restart');
        $this->oOutput->writeln('<info>done</info>');
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
        $this->oOutput->write('Randomising root password... ');
        $oSsh->exec('usermod --password $(openssl rand -base64 32) root');
        $this->oOutput->writeln('<info>done</info>');
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
        $this->oOutput->write('Setting domain as env var... ');
        $oSsh->exec('sed -E -i \'s/DOMAIN="localhost"/DOMAIN="' . $this->sDomain . '"/g\' /home/deploy/www/.env');
        $this->oOutput->writeln('<info>done</info>');

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
    private function setHostname(SSH2 $oSsh): self
    {
        $this->oOutput->write('Setting {domain}-{environment} as hostname... ');
        $sHostname = strtolower(
            sprintf(
                '%s-%s',
                str_replace('.', '-', $this->sDomain),
                static::ENVIRONMENTS[$this->sEnvironment]
            )
        );
        $oSsh->exec('hostname ' . $sHostname);
        $oSsh->exec('sed -Ei "s:127\.0\.1\.1.+:127.0.1.1 ' . $sHostname . ':g" /etc/hosts');
        $oSsh->exec('echo "' . $sHostname . '" > /etc/hostname');
        $this->oOutput->writeln('<info>done</info>');

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
            $this->oOutput->write('Adding deploy key... ');
            $oSsh->exec('echo "' . $this->sDeployKey . '" >> /home/deploy/.ssh/authorized_keys');
            $this->oOutput->writeln('<info>done</info>');
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Whether mySQL needs configured
     *
     * @return bool
     */
    private function shouldConfigureMySQL(): bool
    {
        return preg_match('/-mysql(57|80)/', $this->oImage->getLabel());
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

        $this->oOutput->write('Configuring database... ');

        $sConfig = $oSsh->exec(
            sprintf(
                '/root/mysql-setup-db.sh %s %s',
                strtolower(static::ENVIRONMENTS[$this->sEnvironment]),
                strtolower(
                    sprintf(
                        '%s_%s',
                        str_replace('.', '_', preg_replace('/[^a-zA-Z0-9.]/', '', $this->sDomain)),
                        static::ENVIRONMENTS[$this->sEnvironment]
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
            $this->oOutput->writeln('<error>' . $sError . '</error>');
        } else {
            $this->oOutput->writeln('<info>done</info>');
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

        $this->oOutput->write('Securing MySQL... ');
        $oSsh->exec('echo $(openssl rand -base64 32) > /root/.mysql_root_password');
        $oSsh->exec('$MYSQL_ROOT_PW = $(cat /root/.mysql_root_password) &&  mysql_secure_installation --use-default -p${MYSQL_ROOT_PW}');
        $this->oOutput->writeln('<info>done</info>');

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
        if ($bEnableBackups && empty($this->oDbConfig->error)) {

            $this->oOutput->write('Configuring backups... ');
            $oSsh->exec('echo \'export DOMAIN="' . $this->sDomain . '"\' >> /root/.backupconfig');
            $oSsh->exec('echo \'export S3_ACCESS_KEY="' . $this->oBackupAccount->getLabel() . '"\' >> /root/.backupconfig');
            $oSsh->exec('echo \'export S3_ACCESS_SECRET="' . $this->oBackupAccount->getToken() . '"\' >> /root/.backupconfig');
            $oSsh->exec('echo \'export S3_BUCKET="shed-backups"\' >> /root/.backupconfig');
            $oSsh->exec('echo \'export MYSQL_HOST="127.0.0.1"\' >> /root/.backupconfig');
            $oSsh->exec('echo \'export MYSQL_USER="' . $this->oDbConfig->user . '"\' >> /root/.backupconfig');
            $oSsh->exec('echo \'export MYSQL_PASSWORD="' . $this->oDbConfig->password . '"\' >> /root/.backupconfig');
            $oSsh->exec('echo \'export MYSQL_DATABASE="' . reset($this->oDbConfig->databases) . '"\' >> /root/.backupconfig');
            $this->oOutput->writeln('<info>done</info>');
        }

        return $this;
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
        $this->oOutput->writeln('');
        if ($this->confirm('Would you like to configure an SSL certificate for this server? [default: <info>yes</info>]')) {

            $this->oOutput->writeln('');
            $this->oOutput->writeln('Ensure DNS records have been deployed for:');
            $this->oOutput->writeln('- A <info>' . $oServer->getIp() . '</info> ' . $this->sDomain);
            $this->oOutput->writeln('- A <info>' . $oServer->getIp() . '</info> www.' . $this->sDomain . ' <comment>(optional)</comment>');
            $this->oOutput->writeln('');

            $this->oOutput->write('Waiting for DNS to propagate... ');
            $iStart = time();

            do {

                sleep(10);

                if (time() - $iStart >= static::SSL_TIMEOUT) {

                    $this->oOutput->writeln('<error>timeout</error>');
                    $this->warning([
                        'Timed out waiting for DNS to propagate (timeout: ' . static::SSL_TIMEOUT . ' seconds)',
                        'You will need to manually configure SSL: SSH in as root and execute `ssl-create`',
                    ]);
                    $bResolved = true;

                } else {

                    $aRecords  = array_filter((array) dns_get_record($this->sDomain, DNS_A));
                    $aRecord   = reset($aRecords);
                    $bResolved = !empty($aRecord['ip']) && $aRecord['ip'] == $oServer->getIp();

                    if ($bResolved) {

                        $this->oOutput->writeln('<info>done</info>');
                        $this->oOutput->write('Generating certificates... ');
                        $oSsh->exec('ssl-create');
                        $this->oOutput->writeln('<info>done</info>');
                        $this->oOutput->write('Restarting Apache... ');
                        $oSsh->exec('service apache2 restart');
                        $this->oOutput->writeln('<info>done</info>');
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

        $this->oOutput->write('Running post-install scripts... ');
        $sOutput = $oSsh->exec($sCommand);

        if (!empty($sOutput)) {
            $this->oProvisionOutput = json_decode($sOutput);
            if (json_last_error()) {
                $this->oOutput->writeln('<error>ERROR</error>');
                $this->oOutput->writeln('<error>Failed to decode output of provisioning script</error>');
                $this->oOutput->writeln('<error>Output: ' . $sOutput . '</error>');
            } else {
                $this->oOutput->writeln('<info>done</info>');
            }
        } else {
            $this->oOutput->writeln('<info>done</info>');
        }

        return $this;
    }
}
