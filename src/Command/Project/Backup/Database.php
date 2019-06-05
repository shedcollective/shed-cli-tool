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
use Shed\Cli\Project\Backup;
use Symfony\Component\Console\Input\InputOption;

final class Database extends Backup
{
    /**
     * The MySQL Host to use for the backup
     *
     * @var string
     */
    protected $sMysqlHost = '127.0.0.1';

    /**
     * The MySQL User to use for the backup
     *
     * @var string
     */
    protected $sMysqlUser = null;

    /**
     * The MySQL Password to use for the backup
     *
     * @var string
     */
    protected $sMysqlPassword = null;

    /**
     * The Database to backup
     *
     * @var string[]
     */
    protected $aMysqlDatabases = [];

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('project:backup:database')
            ->setDescription('Back up a project\'s database')
            ->setHelp('This command will backup a project\'s database to S3')
            ->addOption(
                'mysql-host',
                'H',
                InputOption::VALUE_REQUIRED,
                'The MySQL Host to use',
                $this->sMysqlHost
            )
            ->addOption(
                'mysql-user',
                'u',
                InputOption::VALUE_REQUIRED,
                'The MySQL User to use',
                $this->sMysqlUser
            )
            ->addOption(
                'mysql-password',
                'p',
                InputOption::VALUE_REQUIRED,
                'The MySQL Password to use',
                $this->sMysqlPassword
            )
            ->addOption(
                'mysql-database',
                'd',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'The MySQL Database to backup',
                $this->aMysqlDatabases
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
            ->banner('Backup a project\'s databases')
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
        parent::checkEnvironment(['mysqldump', 'tar']);
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the variables
     *
     * @return $this
     * @throws CliException
     */
    protected function setVariables(): Backup
    {
        parent::setVariables();

        $this->sMysqlHost      = $this->oInput->getOption('mysql-host');
        $this->sMysqlUser      = $this->oInput->getOption('mysql-user');
        $this->sMysqlPassword  = $this->oInput->getOption('mysql-password');
        $this->aMysqlDatabases = $this->oInput->getOption('mysql-database');

        if (empty($this->sMysqlHost)) {
            throw new CliException('Missing required option "mysql-host" [--domain]');
        } elseif (empty($this->sMysqlUser)) {
            throw new CliException('Missing required option "mysql-user" [--mysql-user]');
        } elseif (empty($this->sMysqlPassword)) {
            throw new CliException('Missing required option "mysql-password" [--mysql-password]');
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
        $aOptions = [
            'Domain'         => $this->sDomain,
            'S3 Key'         => $this->sS3Key,
            'S3 Secret'      => $this->sS3Secret ? '<info>set</info>' : '',
            'S3 Bucket'      => $this->sS3Bucket,
            'MySQL Host'     => $this->sMysqlHost,
            'MySQL User'     => $this->sMysqlUser,
            'MySQL Password' => $this->sMysqlPassword ? '<info>set</info>' : '',
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
     * @throws CliException
     * @throws Exception
     */
    private function backupProject(): Database
    {
        foreach ($this->aMysqlDatabases as $sMysqlDatabase) {

            try {

                $aFiles = [];
                $this->oOutput->writeln('');
                $this->oOutput->writeln('Backing up <comment>' . $sMysqlDatabase . '</comment>...');

                //  Dump the DB
                $this->oOutput->write('↳ Dumping... ');
                $aFiles['TEMP'] = $this->sTmpDir . DIRECTORY_SEPARATOR . md5(microtime(true)) . DIRECTORY_SEPARATOR . $sMysqlDatabase . '.sql';
                mkdir(dirname($aFiles['TEMP']));

                $sDumpCommand = implode(' ', [
                    'mysqldump',
                    '-h' . $this->sMysqlHost,
                    '-u' . $this->sMysqlUser,
                    '-p' . $this->sMysqlPassword,
                    $sMysqlDatabase,
                    '> ' . $aFiles['TEMP'],
                ]);

                $this->exec($sDumpCommand);
                $this->oOutput->writeln('<info>done</info>');

                //  Compress the file
                $this->oOutput->write('↳ Compressing... ');
                $aFiles['COMPRESSED'] = $aFiles['TEMP'] . '.tar.gz';
                $this->exec(implode(' ', [
                    'tar -czf ' . $aFiles['COMPRESSED'],
                    ' -C ' . dirname($aFiles['TEMP']) . ' ' . basename($aFiles['TEMP']),
                ]));
                $this->oOutput->writeln('<info>done</info>');

                //  Push to S3
                $this->pushToS3($aFiles['COMPRESSED'], 'db/' . $sMysqlDatabase);

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
        Config::set('project.backup.database', (new DateTime())->format('Y-m-d H:i:s'));

        $this->oOutput->writeln('');
        $this->oOutput->writeln('🎉 Completed backup job');
        $this->oOutput->writeln('');
        return $this;
    }
}
