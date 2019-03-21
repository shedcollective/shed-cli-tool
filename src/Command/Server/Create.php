<?php

namespace Shed\Cli\Command\Server;

use Shed\Cli\Command;
use Shed\Cli\Exceptions\Environment\NotValidException;
use Shed\Cli\Helper\System;
use Shed\Cli\Interfaces\Provider;
use Shed\Cli\Entity\Provider\Account;
use Shed\Cli\Entity\Provider\Image;
use Shed\Cli\Entity\Provider\Region;
use Shed\Cli\Entity\Provider\Size;
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
     */
    const FRAMEWORKS = [
        self::FRAMEWORK_NAILS,
        self::FRAMEWORK_LARAVEL,
        self::FRAMEWORK_WORDPRESS,
        self::FRAMEWORK_STATIC,
    ];

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
     * An SSH key to assign to the deployhq user
     *
     * @var string
     */
    private $sDeployKey = '';

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure()
    {
        //  @todo (Pablo - 2019-02-12) - Auto-detect supported backend environments
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
                'An optional public key to assign the deployhq user'
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

        if ($this->confirmVariables()) {
            $this->createServer();
        }

        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    /**
     * Validates that the environment is usable
     *
     * @throws NotValidException
     *
     * @return $this
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
        if (empty($sOption)) {
            $this->sEnvironment = $this->choose(
                'Environment:',
                static::ENVIRONMENTS,
                null,
                [$this, 'validateEnvironment']
            );
        } else {
            if ($this->validateEnvironment($sOption)) {
                $this->sEnvironment = $sOption;
                $this->oOutput->writeln('<comment>Environment</comment>: ' . $this->sEnvironment);
            } else {
                $this->sEnvironment = $this->choose(
                    'Environment:',
                    static::ENVIRONMENTS,
                    null,
                    [$this, 'validateEnvironment']
                );
            }
        }

        $this->sEnvironment = strtoupper($this->sEnvironment);

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

        if (!in_array(strtoupper($sEnvironment), static::ENVIRONMENTS)) {
            $this->error(array_filter([
                '"' . $sEnvironment . '" is not a valid Environment',
                'Should be one of: ' . implode(', ', static::ENVIRONMENTS),
            ]));
            return false;
        }

        return true;
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
        if (empty($sOption)) {
            $this->sFramework = $this->choose(
                'Framework:',
                static::FRAMEWORKS,
                null,
                [$this, 'validateFramework']
            );
        } else {
            if ($this->validateFramework($sOption)) {
                $this->sFramework = $sOption;
                $this->oOutput->writeln('<comment>Framework</comment>: ' . $this->sFramework);
            } else {
                $this->sFramework = $this->choose(
                    'Framework:',
                    static::FRAMEWORKS,
                    null,
                    [$this, 'validateFramework']
                );
            }
        }

        $this->sFramework = strtoupper($this->sFramework);

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

        if (!in_array(strtoupper($sFramework), static::FRAMEWORKS)) {
            $this->error(array_filter([
                '"' . $sFramework . '" is not a valid Framework',
                'Should be one of: ' . implode(', ', static::FRAMEWORKS),
            ]));
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Looks up and sets the backend framework
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

        if (array_key_exists($sDefault,
            $aOptions)) {
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
        $aKeywords[] = $this->sEnvironment;
        $aKeywords[] = $this->sFramework;
        $aKeywords[] = $this->oImage->getLabel();
        $aKeywords   = array_values(
            array_filter(
                array_unique(
                    array_map(
                        function ($sKeyword) {
                            $sKeyword = strtolower($sKeyword);
                            $sKeyword = preg_replace('/[^a-z0-0 \-]/', '', $sKeyword);
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
        $this->oOutput->writeln('Does this all look OK?');
        $this->oOutput->writeln('');
        $this->oOutput->writeln('<comment>Domain</comment>: ' . $this->sDomain);
        $this->oOutput->writeln('<comment>Environment</comment>: ' . static::ENVIRONMENTS[$this->sEnvironment]);
        $this->oOutput->writeln('<comment>Framework</comment>: ' . static::FRAMEWORKS[$this->sFramework]);
        $this->oOutput->writeln('<comment>Provider</comment>: ' . $this->oProvider->getLabel());
        $this->oOutput->writeln('<comment>Account</comment>: ' . $this->oAccount->getLabel());
        $this->oOutput->writeln('<comment>Region</comment>: ' . $this->oRegion->getLabel());
        $this->oOutput->writeln('<comment>Size</comment>: ' . $this->oSize->getLabel());
        $this->oOutput->writeln('<comment>Image</comment>: ' . $this->oImage->getLabel());

        foreach ($this->aProviderOptions as $sKey => $oOption) {
            $this->oOutput->writeln(
                $oOption->summarise($this->aProviderOptions)
            );
        }

        $this->oOutput->writeln('<comment>Keywords</comment>: ' . implode(', ', $this->aKeywords));
        $this->oOutput->writeln('<comment>Deploy Key</comment>: ' . ($this->sDeployKey ? 'Set' : 'None'));

        $this->oOutput->writeln('');
        return $this->confirm('Continue?');
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new project
     *
     * @return $this
     */
    private function createServer(): Create
    {
        $this->oOutput->writeln('');
        $this->oOutput->write('Creating server...');

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
            $this->sDeployKey
        );

        //  @todo (Pablo - 2019-02-07) - Record server details at shedcollective.com

        $this->oOutput->writeln('<info>done!</info>');
        $this->oOutput->writeln('');
        $this->oOutput->writeln('<comment>ID</comment>:         ' . $oServer->getId());
        $this->oOutput->writeln('<comment>IP Address</comment>: ' . $oServer->getIp());
        $this->oOutput->writeln('<comment>Domain</comment>:     ' . $oServer->getDomain());
        $this->oOutput->writeln('<comment>Disk</comment>:       ' . $oServer->getDisk()->getLabel());
        $this->oOutput->writeln('<comment>Image</comment>:      ' . $oServer->getImage()->getLabel());
        $this->oOutput->writeln('<comment>Region</comment>:     ' . $oServer->getRegion()->getLabel());
        $this->oOutput->writeln('<comment>Size</comment>:       ' . $oServer->getSize()->getLabel());
        $this->oOutput->writeln('');
        $this->warning(array_filter([
            'It may take a few minutes before the server is fully configured',
        ]));
        $this->oOutput->writeln('');

        return $this;
    }
}
