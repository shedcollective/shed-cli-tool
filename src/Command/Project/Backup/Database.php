<?php

namespace Shed\Cli\Command\Project\Backup;

use Shed\Cli\Exceptions\Directory\FailedToCreateException;
use Shed\Cli\Exceptions\Environment\NotValidException;
use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Exceptions\Zip\CannotOpenException;
use Shed\Cli\Helper\System;
use Shed\Cli\Project\Backup;
use Symfony\Component\Console\Input\InputOption;

final class Database extends Backup
{
    /**
     * The MySQL Host to use for the backup
     *
     * @var string
     */
    protected $sMysqlHost = '';

    /**
     * The MySQL User to use for the backup
     *
     * @var string
     */
    protected $sMysqlUser = '';

    /**
     * The MySQL Password to use for the backup
     *
     * @var string
     */
    protected $sMysqlPassword = '';

    /**
     * The Database to backup
     *
     * @var array
     */
    protected $aMysqlDatabases = [];

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this
            ->setName('project:backup:db')
            ->setDescription('Back up a project\'s database')
            ->setHelp('This command will backup a project\'s database to S3')
            ->addOption(
                'mysql-host',
                'H',
                InputOption::VALUE_REQUIRED,
                'The MySQL Host to use'
            )
            ->addOption(
                'mysql-user',
                'u',
                InputOption::VALUE_REQUIRED,
                'The MySQL User to use'
            )
            ->addOption(
                'mysql-password',
                'p',
                InputOption::VALUE_REQUIRED,
                'The MySQL Password to use'
            )
            ->addOption(
                'mysql-database',
                'd',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'The MySQL Database to backup'
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Execute the command
     *
     * @return int
     * @throws CannotOpenException
     * @throws CommandFailedException
     * @throws NotValidException
     * @throws FailedToCreateException
     */
    protected function go(): int
    {
        $this
            ->banner('Backup a project\'s database')
            ->checkEnvironment()
            ->setVariables();

        if ($this->confirmVariables()) {
            $this->backupProject();
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
    private function checkEnvironment(): Database
    {
        if (!function_exists('exec')) {
            throw new NotValidException('Missing function exec()');
        }

        $aRequiredCommands = ['mysqldump', 'zip'];
        foreach ($aRequiredCommands as $sRequiredCommand) {
            if (!System::commandExists($sRequiredCommand)) {
                throw new NotValidException($sRequiredCommand . ' is not installed');
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Configures the create command
     *
     * @return $this
     */
    private function setVariables(): Database
    {
        //  @todo (Pablo - 2019-04-01) - Set all variables
        return $this;
    }

    // --------------------------------------------------------------------------

    //  @todo (Pablo - 2019-04-01) - Variable setting methods

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
        $aOptions = [
            'Domain'         => $this->sDomain,
            'S3 Token'       => $this->sS3Token,
            'S3 Secret'      => 'Set',
            'S3 Bucket'      => $this->sS3Bucket,
            'MySQL Host'     => $this->sMysqlHost,
            'MySQL User'     => $this->sMysqlUser,
            'MySQL Password' => 'Set',
        ];
        $iCounter = 0;
        foreach ($this->aMysqlDatabases as $sDatabase) {
            $aOptions['MySQL Database ' . ++$iCounter] = $sDatabase;
        }
        $this->keyValueList($aOptions);
        return $this->confirm('Continue?');
    }

    // --------------------------------------------------------------------------

    /**
     * Backs up the project
     *
     * @return $this
     * @throws CannotOpenException
     * @throws CommandFailedException
     * @throws FailedToCreateException
     */
    private function backupProject(): Database
    {
        $this->oOutput->writeln('');

        //  @todo (Pablo - 2019-04-01) - Perform backup and sync

        $this->oOutput->writeln('');
        $this->oOutput->writeln('🎉 Project has been backed up to <comment>~~~bucket-name/domain/database/backup-file~~~</comment>');
        $this->oOutput->writeln('');
        return $this;
    }
}
