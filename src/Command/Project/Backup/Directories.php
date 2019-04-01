<?php

namespace Shed\Cli\Command\Project\Backup;

use Shed\Cli\Exceptions\Directory\FailedToCreateException;
use Shed\Cli\Exceptions\Environment\NotValidException;
use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Exceptions\Zip\CannotOpenException;
use Shed\Cli\Helper\System;
use Shed\Cli\Project\Backup;
use Symfony\Component\Console\Input\InputOption;

final class Directories extends Backup
{
    /**
     * The directory to backup
     *
     * @var array
     */
    protected $aDirectories = ['/var/www/1', '/var/www/2'];

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this
            ->setName('project:backup:directories')
            ->setDescription('Back up a project\'s directories')
            ->setHelp('This command will backup project directories to S3')
            ->addOption(
                'directory',
                'd',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'The directory to backup'
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
            ->banner('Backup a project\'s directories')
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
    private function checkEnvironment(): Directories
    {
        if (!function_exists('exec')) {
            throw new NotValidException('Missing function exec()');
        }

        $aRequiredCommands = ['zip'];
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
    private function setVariables(): Directories
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
            'Domain'    => $this->sDomain,
            'S3 Token'  => $this->sS3Token,
            'S3 Secret' => 'Set',
            'S3 Bucket' => $this->sS3Bucket,
        ];
        $iCounter = 0;
        foreach ($this->aDirectories as $sDirectory) {
            $aOptions['Directory ' . ++$iCounter] = $sDirectory;
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
    private function backupProject(): Directories
    {
        $this->oOutput->writeln('');

        //  @todo (Pablo - 2019-04-01) - Perform backup and sync

        $this->oOutput->writeln('');
        $this->oOutput->writeln('🎉 Project has been backed up to <comment>~~~bucket-name/domain/directories/backup-file~~~</comment>');
        $this->oOutput->writeln('');
        return $this;
    }
}
