<?php

namespace Shed\Cli\Project;

use DateTime;
use Exception;
use Shed\Cli\Command;
use Shed\Cli\Entity\Provider\Account;
use Shed\Cli\Exceptions\CliException;
use Shed\Cli\Exceptions\Environment\NotValidException;
use Shed\Cli\Helper\System;
use Symfony\Component\Console\Input\InputOption;

abstract class Backup extends Command
{
    /**
     * The domain being backed up
     *
     * @var string
     */
    protected $sDomain = null;

    /**
     * The S3 Access Key to use for the backup
     *
     * @var string
     */
    protected $sS3Key = null;

    /**
     * The S3 Access Secret to use for the backup
     *
     * @var string
     */
    protected $sS3Secret = null;

    /**
     * The S3 Bucket to use for the backup
     *
     * @var string
     */
    protected $sS3Bucket = null;

    /**
     * The temporary directory to use
     *
     * @var string
     */
    protected $sTmpDir = null;

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'domain',
                'D',
                InputOption::VALUE_REQUIRED,
                'The domain being backed up',
                $this->sDomain
            )
            ->addOption(
                's3-key',
                'k',
                InputOption::VALUE_REQUIRED,
                'The S3 Access Key to use',
                $this->sS3Key
            )
            ->addOption(
                's3-secret',
                's',
                InputOption::VALUE_REQUIRED,
                'The S3 Access Secret to use',
                $this->sS3Secret
            )
            ->addOption(
                's3-bucket',
                'b',
                InputOption::VALUE_REQUIRED,
                'The S3 bucket to use',
                $this->sS3Bucket
            )
            ->addOption(
                'tmp-dir',
                't',
                InputOption::VALUE_OPTIONAL,
                'The temporary directory to use',
                $this->sTmpDir ?? sys_get_temp_dir()
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Validates that the environment is usable
     *
     * @param array $aCommands Any commands to require
     *
     * @return $this
     * @throws NotValidException
     *
     */
    protected function checkEnvironment(array $aCommands = []): Backup
    {
        if (!function_exists('exec')) {
            throw new NotValidException('Missing function exec()');
        }

        $aRequiredCommands = array_merge(['s3cmd'], $aCommands);
        foreach ($aRequiredCommands as $sRequiredCommand) {
            if (!System::commandExists($sRequiredCommand)) {
                throw new NotValidException($sRequiredCommand . ' is not installed');
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the variables
     *
     * @return $this
     * @throws CliException
     */
    protected function setVariables(): self
    {
        $this->sDomain   = $this->oInput->getOption('domain');
        $this->sS3Key    = $this->oInput->getOption('s3-key');
        $this->sS3Secret = $this->oInput->getOption('s3-secret');
        $this->sS3Bucket = $this->oInput->getOption('s3-bucket');
        $this->sTmpDir   = $this->oInput->getOption('tmp-dir');

        if (empty($this->sDomain)) {
            throw new CliException('Missing required option "domain" [--domain]');
        } elseif (empty($this->sS3Bucket)) {
            throw new CliException('Missing required option "s3-bucket" [--s3-bucket]');
        } elseif (empty($this->sTmpDir)) {
            throw new CliException('Missing required option "tmp-dir" [--tmp-dir]');
        }

        //  If no access credentials are given, auto-detect from configs
        if (empty($this->sS3Key)) {

            /** @var Account[] $aAccounts */
            $aAccounts = Command\Auth\Amazon::getAccounts();

            if (count($aAccounts) === 0) {
                throw new CliException(
                    'No Amazon credentials registered, specify details using --s3-key  and --s3-secret'
                );
            } elseif (count($aAccounts) === 1) {
                $oAccount = reset($aAccounts);
            } else {

                $iAccountIndex = $this->choose(
                    'Amazon Account',
                    array_map(function (Account $oAccount) {
                        return $oAccount->getLabel();
                    }, $aAccounts)
                );

                $oAccount = $aAccounts[$iAccountIndex];
            }

            $this->sS3Key    = $oAccount->getLabel();
            $this->sS3Secret = $oAccount->getToken();

        } elseif (empty($this->sS3Secret)) {
            $oAccount        = Command\Auth\Amazon::getAccountByLabel($this->sS3Key);
            $this->sS3Key    = $oAccount->getLabel();
            $this->sS3Secret = $oAccount->getToken();
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Pushes a file to S3
     *
     * @param string $sFile        The file on disk
     * @param string $sDestination The file on S3
     *
     * @throws CliException
     * @throws Exception
     */
    protected function pushToS3(string $sFile, string $sDestination)
    {
        $oNow         = new DateTime();
        $sDestination = $this->sDomain . '/' . $sDestination . '-' . $oNow->format('Y-m-d-H-i-s') . '.tar.gz';
        $this->oOutput->write('↳ Pushing to S3 (<info>s3://' . $this->sS3Bucket . '/' . $sDestination . '</info>)... ');

        $sS3Command = implode(' ', [
            's3cmd',
            'put ' . $sFile,
            's3://' . $this->sS3Bucket . '/' . $sDestination,
            '--access_key=' . $this->sS3Key,
            '--secret_key=' . $this->sS3Secret,
        ]);

        $this->exec($sS3Command);

        $this->oOutput->writeln('<info>done</info>');
    }

    // --------------------------------------------------------------------------

    /**
     * Execute a command and throw an exception if it fails
     *
     * @param string $sCommand The command
     *
     * @throws CliException
     */
    protected function exec(string $sCommand): void
    {
        $aOutput    = [];
        $iReturnVar = System::exec($sCommand . ' 2>&1', $aOutput);
        if ($iReturnVar !== 0) {
            $e = new CliException('Command failed: "' . $sCommand . '"');
            $e->setDetails($aOutput);
            throw $e;
        }
    }
}
