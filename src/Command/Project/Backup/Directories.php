<?php

namespace Shed\Cli\Command\Project\Backup;

use DateTime;
use Exception;
use Shed\Cli\Exceptions\CliException;
use Shed\Cli\Exceptions\Directory\FailedToCreateException;
use Shed\Cli\Exceptions\Environment\NotValidException;
use Shed\Cli\Exceptions\System\CommandFailedException;
use Shed\Cli\Exceptions\Zip\CannotOpenException;
use Shed\Cli\Helper\Config;
use Shed\Cli\Helper\Debug;
use Shed\Cli\Project\Backup;
use Symfony\Component\Console\Input\InputOption;

final class Directories extends Backup
{
    /**
     * The directories to backup
     *
     * @var string[]
     */
    protected $aDirectories = [];

    /**
     * Any directories to exclude
     *
     * @var string[]
     */
    protected $aExclude = [];

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('project:backup:directories')
            ->setDescription('Back up a project\'s directories')
            ->setHelp('This command will backup project directories to S3')
            ->addOption(
                'directory',
                'd',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'The directory to backup',
                $this->aDirectories
            )
            ->addOption(
                'exclude',
                'e',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Any directories to exclude',
                $this->aDirectories
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
     * @param array $aCommands Any commands to require
     *
     * @return $this
     * @throws NotValidException
     *
     */
    protected function checkEnvironment(array $aCommands = []): Backup
    {
        parent::checkEnvironment(['tar']);
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the variables
     *
     * @return $this
     */
    protected function setVariables(): Backup
    {
        parent::setVariables();

        $this->aDirectories = $this->oInput->getOption('directory');
        $this->aExclude     = $this->oInput->getOption('exclude');

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
        $aOptions = [
            'Domain'    => $this->sDomain,
            'S3 Key'    => $this->sS3Key,
            'S3 Secret' => $this->sS3Secret ? '<info>set</info>' : '',
            'S3 Bucket' => $this->sS3Bucket,
        ];
        $iCounter = 0;
        foreach ($this->aDirectories as $sDirectory) {
            $aOptions['Directory ' . ++$iCounter] = $sDirectory;
        }
        $iCounter = 0;
        foreach ($this->aExclude as $sDirectory) {
            $aOptions['Exclude ' . ++$iCounter] = $sDirectory;
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
     * @throws CliException
     * @throws Exception
     */
    private function backupProject(): Directories
    {
        foreach ($this->aDirectories as $sDirectory) {

            try {

                $aFiles = [];
                $this->oOutput->writeln('');
                $this->oOutput->writeln('Backing up <comment>' . $sDirectory . '</comment>...');

                $sSafeDirectory = ltrim(str_replace(DIRECTORY_SEPARATOR, '_', $sDirectory), '_');

                //  Compress the file
                $this->oOutput->write('↳ Compressing... ');
                $aFiles['COMPRESSED'] = $this->sTmpDir . DIRECTORY_SEPARATOR . md5(microtime(true)) . '.tar.gz';
                $this->exec($this->compileTarCommand($aFiles['COMPRESSED'], $sDirectory));
                $this->oOutput->writeln('<info>done</info>');

                //  Push to S3
                $this->pushToS3($aFiles['COMPRESSED'], 'dir/' . $sSafeDirectory);

            } catch (CliException $e) {

                $this->oOutput->writeln('');
                $this->error(
                    array_filter(
                        array_merge(
                            ['An error occurred:'],
                            [$e->getMessage()],
                            $e->getDetails()
                        )
                    )
                );

                //  @todo (Pablo - 2019-05-24) - Contact someone about this?

            } finally {

                if (!empty($aFiles)) {
                    $this->oOutput->write('↳ Cleaning up... ');
                    foreach ($aFiles as $sFile) {
                        if (is_file($sFile)) {
                            unlink($sFile);
                        }
                    }
                    $this->oOutput->writeln('<info>done</info>');
                }
            }
        }

        //  Not the last completed backup time
        Config::set('project.backup.directories', (new DateTime())->format('Y-m-d H:i:s'));

        $this->oOutput->writeln('');
        $this->oOutput->writeln('🎉 Completed backup job');
        $this->oOutput->writeln('');
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Compiles the Tar command
     *
     * @param string $sArchive The target for the archive
     * @param string $sSource  The source directory being backed up
     *
     * @return string
     */
    private function compileTarCommand(string $sArchive, string $sSource): string
    {
        return sprintf(
            'tar -czf "%s" %s -C "%s" .',
            $sArchive,
            implode(' ', array_map(function (string $sDir) use ($sSource) {
                return sprintf(
                    '--exclude "%s"',
                    preg_replace('#' . $sSource . DIRECTORY_SEPARATOR . '#', '', $sDir)
                );
            }, $this->aExclude)),
            $sSource
        );
    }
}
