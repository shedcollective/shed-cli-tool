<?php

namespace Shed\Cli\Command\Database;

use Shed\Cli\Entity\DatabaseConnection;
use Shed\Cli\Exceptions\CliException;
use Shed\Cli\Exceptions\Environment\NotValidException;
use Symfony\Component\Console\Input\InputArgument;

final class Sync extends DatabaseCommand
{
    // --------------------------------------------------------------------------

    /**
     * @var DatabaseConnection
     */
    private DatabaseConnection $oSource;

    /**
     * @var DatabaseConnection
     */
    private DatabaseConnection $oTarget;

    /**
     * @var string
     */
    private string $sSourceDb;

    /**
     * @var string
     */
    private string $sTargetDb;

    /**
     * @var string|null
     */
    private ?string $sPresetLabel = null;

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this
            ->setName('db:sync')
            ->setDescription('Sync a database from a remote connection to a local one')
            ->setHelp('Streams a remote database directly to a local MySQL instance via SSH. Run db:connection to manage saved connections, db:preset to manage presets.')
            ->addArgument(
                'preset',
                InputArgument::OPTIONAL,
                'Name of a saved preset to run non-interactively (see db:preset)'
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Execute the command
     *
     * @return int
     * @throws CliException
     * @throws NotValidException
     */
    protected function go(): int
    {
        try {

            $this->banner('Database Sync');
            $this->checkEnvironment();

            $sPreset = trim($this->oInput->getArgument('preset') ?? '');

            if (!empty($sPreset)) {
                $this->loadPreset($sPreset);
            } else {
                $this->chooseSource();
                $this->chooseTarget();
            }

            $this->confirmSync();
            $this->executeSync();

        } finally {
            $this->cleanup();
        }

        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    /**
     * Validate that required system commands are available
     *
     * @return $this
     * @throws NotValidException
     */
    private function checkEnvironment(): self
    {
        foreach (['mysql', 'mysqldump', 'ssh'] as $sCmd) {
            exec('which ' . escapeshellarg($sCmd) . ' 2>/dev/null', $aOut, $iCode);
            if ($iCode !== 0) {
                throw new NotValidException(
                    'Required command "' . $sCmd . '" not found. Please install it and try again.'
                );
            }
            $aOut = [];
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Resolve a saved preset into source/target connection and database properties
     *
     * @param string $sLabel
     *
     * @throws CliException
     */
    private function loadPreset(string $sLabel): void
    {
        $oPreset = Preset::getPresetByLabel($sLabel);

        if ($oPreset === null) {
            throw new CliException('No preset found with name "' . $sLabel . '". Run <info>shed db:preset</info> to list available presets.');
        }

        $oSource = Connection::getConnectionByLabel($oPreset->getSourceConnection());
        $oTarget = Connection::getConnectionByLabel($oPreset->getTargetConnection());

        if ($oSource === null) {
            throw new CliException('Source connection "' . $oPreset->getSourceConnection() . '" not found. It may have been deleted.');
        }

        if ($oTarget === null) {
            throw new CliException('Target connection "' . $oPreset->getTargetConnection() . '" not found. It may have been deleted.');
        }

        if ($oTarget->isProduction()) {
            throw new CliException('Target connection "' . $oTarget->getLabel() . '" is marked as production and cannot be a sync target.');
        }

        if ($oSource->getLabel() === $oTarget->getLabel() && $oPreset->getSourceDatabase() === $oPreset->getTargetDatabase()) {
            throw new CliException('Source and target cannot be the same database.');
        }

        $this->oSource      = $oSource;
        $this->sSourceDb    = $oPreset->getSourceDatabase();
        $this->oTarget      = $oTarget;
        $this->sTargetDb    = $oPreset->getTargetDatabase();
        $this->sPresetLabel = $oPreset->getLabel();
    }

    // --------------------------------------------------------------------------

    /**
     * Interactively choose the source connection and database
     *
     * @return $this
     * @throws CliException
     */
    private function chooseSource(): self
    {
        $this->oOutput->writeln('<comment>Step 1 of 2: Source — the database you are syncing FROM</comment>');

        $this->oSource = $this->pickConnection('Source', preferLocal: false, bExcludeProduction: false);

        $this->oOutput->write('↳ Fetching database list... ');
        $aDatabases = $this->listDatabases($this->oSource);
        $this->oOutput->writeln('<info>done</info>');

        if (empty($aDatabases)) {
            throw new CliException('No accessible databases found on the source connection.');
        }

        $iIdx            = (int) $this->choose('Which database to sync from?', $aDatabases);
        $this->sSourceDb = $aDatabases[$iIdx];

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Interactively choose the target connection and database
     *
     * @return $this
     * @throws CliException
     */
    private function chooseTarget(): self
    {
        $this->oOutput->writeln('');
        $this->oOutput->writeln('<comment>Step 2 of 2: Target — the database you are syncing TO</comment>');

        $this->oTarget = $this->pickConnection('Target', preferLocal: true, bExcludeProduction: true);

        if ($this->oTarget->isRemote()) {
            $this->warning(['Remote targets are not supported in this version. Choose a local connection.']);
            return $this->chooseTarget();
        }

        $this->oOutput->write('↳ Fetching database list... ');
        $aDatabases = $this->listDatabases($this->oTarget);
        $this->oOutput->writeln('<info>done</info>');

        $aDbChoices   = $aDatabases;
        $aDbChoices[] = 'Create a new database';
        $iIdx         = (int) $this->choose('Which database to sync into? (ALL DATA WILL BE REPLACED)', $aDbChoices);

        if ($iIdx === count($aDatabases)) {
            $this->sTargetDb = $this->ask('New database name:') ?? '';
        } else {
            $this->sTargetDb = $aDatabases[$iIdx];
        }

        if ($this->oTarget->getLabel() === $this->oSource->getLabel() && $this->sTargetDb === $this->sSourceDb) {
            $this->warning(['Source and target cannot be the same database. Please choose a different one.']);
            return $this->chooseTarget();
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Show a summary and ask for confirmation before syncing
     *
     * @return $this
     */
    private function confirmSync(): self
    {
        $aDetails = [];
        if ($this->sPresetLabel !== null) {
            $aDetails['Preset'] = $this->sPresetLabel;
        }
        $aDetails = array_merge($aDetails, [
            'Source connection' => $this->oSource->getLabel() . ' [' . $this->oSource->getEnvironment() . ']',
            'Source database'   => $this->sSourceDb,
            'Target connection' => $this->oTarget->getLabel() . ' [' . $this->oTarget->getEnvironment() . ']',
            'Target database'   => $this->sTargetDb,
            'Warning'           => 'ALL DATA in the target database will be replaced',
        ]);

        $this->keyValueList($aDetails, 'Ready to sync — please confirm:');

        if (!$this->confirm('Proceed with sync?')) {
            $this->oOutput->writeln('Sync cancelled.');
            exit(static::EXIT_CODE_SUCCESS);
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Prepare, stream, and finalize the sync
     *
     * @return $this
     * @throws CliException
     */
    private function executeSync(): self
    {
        $this->oOutput->writeln('');
        $this->oOutput->write('Preparing target database... ');
        $this->prepareTarget();
        $this->oOutput->writeln('<info>done</info>');

        $bHasPv = $this->hasPv();

        if (!$bHasPv) {
            $this->oOutput->writeln('');
            $this->oOutput->writeln('<comment>Hint: install `pv` for real-time progress. Streaming...</comment>');
        }

        $this->oOutput->writeln('');

        $sTargetCredFile = $this->writeTempCredentials($this->oTarget->getDbPassword());
        $sCommand        = $this->buildSyncPipeline($sTargetCredFile, $bHasPv);

        $iCode = 0;
        passthru('bash -o pipefail -c ' . escapeshellarg($sCommand), $iCode);

        if ($iCode !== 0) {
            throw new CliException('Sync failed (exit code ' . $iCode . '). Check credentials and connectivity.');
        }

        $this->oOutput->writeln('');
        $this->oOutput->writeln('Sync complete!');
        $this->oOutput->writeln('');

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Drop and recreate the target database, or fall back to dropping all tables
     *
     * @throws CliException
     */
    private function prepareTarget(): void
    {
        if (!$this->tryDropAndRecreate()) {
            $this->dropAllTables();
        }

        $this->execLocalMysql(
            $this->oTarget,
            sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                addslashes($this->sTargetDb)
            )
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Attempt DROP + CREATE DATABASE. Returns false if permission denied.
     *
     * @return bool
     */
    private function tryDropAndRecreate(): bool
    {
        $sSql = sprintf(
            'DROP DATABASE IF EXISTS `%1$s`; CREATE DATABASE `%1$s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            addslashes($this->sTargetDb)
        );

        $sCredFile = $this->writeTempCredentials($this->oTarget->getDbPassword());
        $sCmd      = sprintf(
            'mysql --defaults-extra-file=%s -h %s -P %d -u %s -e %s 2>/dev/null',
            escapeshellarg($sCredFile),
            escapeshellarg($this->oTarget->getDbHost()),
            $this->oTarget->getDbPort(),
            escapeshellarg($this->oTarget->getDbUser()),
            escapeshellarg($sSql)
        );

        exec($sCmd, $aOut, $iCode);
        return $iCode === 0;
    }

    // --------------------------------------------------------------------------

    /**
     * Drop all tables in the target database using SET FOREIGN_KEY_CHECKS workaround
     *
     * @throws CliException
     */
    private function dropAllTables(): void
    {
        $sSql = sprintf(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = '%s' AND table_type = 'BASE TABLE'",
            addslashes($this->sTargetDb)
        );

        $sCredFile = $this->writeTempCredentials($this->oTarget->getDbPassword());
        $sCmd      = sprintf(
            'mysql --defaults-extra-file=%s -h %s -P %d -u %s --skip-column-names -e %s 2>/dev/null',
            escapeshellarg($sCredFile),
            escapeshellarg($this->oTarget->getDbHost()),
            $this->oTarget->getDbPort(),
            escapeshellarg($this->oTarget->getDbUser()),
            escapeshellarg($sSql)
        );

        exec($sCmd, $aTables, $iCode);

        if ($iCode !== 0 || empty($aTables)) {
            return;
        }

        $aStatements = ['SET FOREIGN_KEY_CHECKS = 0'];
        foreach ($aTables as $sTable) {
            $aStatements[] = sprintf(
                'DROP TABLE IF EXISTS `%s`.`%s`',
                addslashes($this->sTargetDb),
                addslashes(trim($sTable))
            );
        }
        $aStatements[] = 'SET FOREIGN_KEY_CHECKS = 1';

        $this->execLocalMysql($this->oTarget, implode('; ', $aStatements));
    }

    // --------------------------------------------------------------------------

    /**
     * Build the full sync pipeline shell command
     *
     * @param string $sTargetCredFile Path to the target MySQL credentials file
     * @param bool   $bUsePv          Whether to pipe through pv for progress
     *
     * @return string
     */
    private function buildSyncPipeline(string $sTargetCredFile, bool $bUsePv): string
    {
        $sDump   = $this->buildDumpCommand();
        $sImport = sprintf(
            'mysql --defaults-extra-file=%s -h %s -P %d -u %s %s',
            escapeshellarg($sTargetCredFile),
            escapeshellarg($this->oTarget->getDbHost()),
            $this->oTarget->getDbPort(),
            escapeshellarg($this->oTarget->getDbUser()),
            escapeshellarg($this->sTargetDb)
        );

        return $bUsePv
            ? $sDump . ' | pv | ' . $sImport
            : $sDump . ' | ' . $sImport;
    }

    // --------------------------------------------------------------------------

    /**
     * Build the mysqldump part of the pipeline, wrapped in SSH for remote sources
     *
     * @return string
     */
    private function buildDumpCommand(): string
    {
        $sDumpArgs = sprintf(
            '--no-tablespaces --hex-blob --single-transaction -h %s -P %d -u %s %s',
            escapeshellarg($this->oSource->getDbHost()),
            $this->oSource->getDbPort(),
            escapeshellarg($this->oSource->getDbUser()),
            escapeshellarg($this->sSourceDb)
        );

        if ($this->oSource->isLocal()) {
            $sCredFile = $this->writeTempCredentials($this->oSource->getDbPassword());
            return sprintf(
                'mysqldump --defaults-extra-file=%s %s',
                escapeshellarg($sCredFile),
                $sDumpArgs
            );
        }

        $sRemoteCmd = sprintf(
            'MYSQL_PWD=%s mysqldump %s',
            escapeshellarg($this->oSource->getDbPassword()),
            $sDumpArgs
        );

        return sprintf(
            'ssh %s %s',
            $this->buildSshTarget($this->oSource),
            escapeshellarg($sRemoteCmd)
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Run a SQL statement against a local MySQL connection
     *
     * @param DatabaseConnection $oConn
     * @param string             $sSql
     *
     * @throws CliException
     */
    private function execLocalMysql(DatabaseConnection $oConn, string $sSql): void
    {
        $sCredFile = $this->writeTempCredentials($oConn->getDbPassword());
        $sCmd      = sprintf(
            'mysql --defaults-extra-file=%s -h %s -P %d -u %s -e %s 2>&1',
            escapeshellarg($sCredFile),
            escapeshellarg($oConn->getDbHost()),
            $oConn->getDbPort(),
            escapeshellarg($oConn->getDbUser()),
            escapeshellarg($sSql)
        );

        exec($sCmd, $aOut, $iCode);

        if ($iCode !== 0) {
            throw new CliException('MySQL command failed: ' . implode(' ', $aOut));
        }
    }
}
