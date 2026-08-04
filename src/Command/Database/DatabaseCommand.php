<?php

namespace Shed\Cli\Command\Database;

use Shed\Cli\Command;
use Shed\Cli\Entity\DatabaseConnection;

abstract class DatabaseCommand extends Command
{
    /**
     * System databases to exclude when listing available databases
     *
     * @var string[]
     */
    const EXCLUDED_DATABASES = [
        'information_schema',
        'performance_schema',
        'sys',
        'mysql',
        'test',
        'testing',
    ];

    // --------------------------------------------------------------------------

    /**
     * Temp directories to clean up after execution
     *
     * @var string[]
     */
    protected array $aTempFiles = [];

    // --------------------------------------------------------------------------

    /**
     * Pick a saved connection or configure a new one inline.
     * When $bExcludeProduction is true, production connections are hidden from the list.
     *
     * @param string $sRole              Label for the prompt (Source/Target)
     * @param bool   $preferLocal        Highlight local connections as preferred
     * @param bool   $bExcludeProduction Exclude production connections from the list
     *
     * @return DatabaseConnection
     */
    protected function pickConnection(
        string $sRole,
        bool   $preferLocal        = false,
        bool   $bExcludeProduction = false
    ): DatabaseConnection {
        $aAll         = Connection::getConnections();
        $aConnections = $bExcludeProduction
            ? array_filter($aAll, fn($o) => !$o->isProduction())
            : $aAll;

        if (empty($aAll)) {
            $this->oOutput->writeln('');
            $this->oOutput->writeln('No saved connections found.');
            $this->oOutput->writeln('Run <info>shed db:connection add</info> to configure one, then re-run this command.');
            $this->oOutput->writeln('');
            exit(static::EXIT_CODE_SUCCESS);
        }

        if ($bExcludeProduction && empty($aConnections)) {
            $this->oOutput->writeln('');
            $this->warning(['All saved connections are marked as production and cannot be used as a target.']);
            $this->oOutput->writeln('Add a non-production connection with <info>shed db:connection add</info>.');
            $this->oOutput->writeln('');
            exit(static::EXIT_CODE_SUCCESS);
        }

        $aConnList = array_values($aConnections);
        $aChoices  = [];
        foreach ($aConnList as $oConn) {
            $sEnvBadge   = match ($oConn->getEnvironment()) {
                DatabaseConnection::ENV_PRODUCTION => ' [PRODUCTION]',
                DatabaseConnection::ENV_STAGING    => ' [staging]',
                default                            => '',
            };
            $sLocalBadge = $preferLocal && $oConn->isLocal() ? ' ★' : '';
            $aChoices[]  = $oConn->getLabel() . ' (' . $oConn->getType() . ')' . $sEnvBadge . $sLocalBadge;
        }
        $aChoices[] = 'Configure a new connection';

        $iIndex = (int) $this->choose($sRole . ' connection:', $aChoices);

        if ($iIndex === count($aConnList)) {
            return $this->configureNewConnection();
        }

        return $aConnList[$iIndex];
    }

    // --------------------------------------------------------------------------

    /**
     * Run the inline connection wizard and optionally save the result.
     * Exits cleanly if the user cancels after a failed connection test.
     *
     * @return DatabaseConnection
     */
    protected function configureNewConnection(): DatabaseConnection
    {
        $oConnCmd = new Connection();
        $oReflect = new \ReflectionClass(Command::class);

        $oPropInput = $oReflect->getProperty('oInput');
        $oPropInput->setValue($oConnCmd, $this->oInput);

        $oPropOutput = $oReflect->getProperty('oOutput');
        $oPropOutput->setValue($oConnCmd, $this->oOutput);

        $oConnCmd->setApplication($this->getApplication());

        $oConn = $oConnCmd->runWizard();

        if ($oConn === null) {
            $this->oOutput->writeln('Run <info>shed db:connection add</info> to configure a connection, then re-run this command.');
            $this->oOutput->writeln('');
            exit(static::EXIT_CODE_SUCCESS);
        }

        $this->oOutput->writeln('');
        if ($this->confirm('Save this connection for future use?')) {
            Connection::saveConnection($oConn);
            $this->oOutput->writeln('<info>Connection saved.</info>');
        }
        $this->oOutput->writeln('');

        return $oConn;
    }

    // --------------------------------------------------------------------------

    /**
     * Query a connection for its list of non-system databases
     *
     * @param DatabaseConnection $oConn
     *
     * @return string[]
     */
    protected function listDatabases(DatabaseConnection $oConn): array
    {
        $aLines = [];

        if ($oConn->isLocal()) {
            $sCredFile = $this->writeTempCredentials($oConn->getDbPassword());
            $sCmd      = sprintf(
                'mysql --defaults-extra-file=%s --skip-column-names -h %s -P %d -u %s -e %s 2>/dev/null',
                escapeshellarg($sCredFile),
                escapeshellarg($oConn->getDbHost()),
                $oConn->getDbPort(),
                escapeshellarg($oConn->getDbUser()),
                escapeshellarg('SHOW DATABASES')
            );
            exec($sCmd, $aLines);
        } else {
            $sRemoteCmd = sprintf(
                'MYSQL_PWD=%s mysql --skip-column-names -h %s -P %d -u %s -e %s',
                escapeshellarg($oConn->getDbPassword()),
                escapeshellarg($oConn->getDbHost()),
                $oConn->getDbPort(),
                escapeshellarg($oConn->getDbUser()),
                escapeshellarg('SHOW DATABASES')
            );
            $sCmd       = sprintf(
                'ssh %s %s 2>/dev/null',
                $this->buildSshTarget($oConn),
                escapeshellarg($sRemoteCmd)
            );
            exec($sCmd, $aLines);
        }

        return array_values(array_filter(
            array_map('trim', $aLines),
            fn($s) => $s !== '' && !in_array(strtolower($s), static::EXCLUDED_DATABASES)
        ));
    }

    // --------------------------------------------------------------------------

    /**
     * Write a MySQL credentials file to a private temp directory.
     * The directory is registered for cleanup on exit.
     *
     * @param string $sPassword
     *
     * @return string Absolute path to the credentials file
     */
    protected function writeTempCredentials(string $sPassword): string
    {
        $sTmpDir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shed_db_' . bin2hex(random_bytes(6));
        $sTmpFile = $sTmpDir . DIRECTORY_SEPARATOR . 'my.cnf';

        mkdir($sTmpDir, 0700, true);
        file_put_contents($sTmpFile, '[client]' . PHP_EOL . 'password=' . $sPassword . PHP_EOL);
        chmod($sTmpFile, 0600);

        $this->aTempFiles[] = $sTmpDir;

        return $sTmpFile;
    }

    // --------------------------------------------------------------------------

    /**
     * Build the SSH target string (user@host or just host, with optional port flag)
     *
     * @param DatabaseConnection $oConn
     *
     * @return string
     */
    protected function buildSshTarget(DatabaseConnection $oConn): string
    {
        $sTarget = $oConn->getSshUser()
            ? $oConn->getSshUser() . '@' . $oConn->getSshHost()
            : (string) $oConn->getSshHost();

        $sPortFlag = $oConn->getSshPort() !== 22
            ? '-p ' . $oConn->getSshPort() . ' '
            : '';

        return $sPortFlag . escapeshellarg($sTarget);
    }

    // --------------------------------------------------------------------------

    /**
     * Check whether pv is installed
     *
     * @return bool
     */
    protected function hasPv(): bool
    {
        exec('which pv 2>/dev/null', $aOut, $iCode);
        return $iCode === 0;
    }

    // --------------------------------------------------------------------------

    /**
     * Remove all temporary credential directories created during this run
     */
    protected function cleanup(): void
    {
        foreach ($this->aTempFiles as $sDir) {
            if (is_dir($sDir)) {
                array_map('unlink', glob($sDir . DIRECTORY_SEPARATOR . '*') ?: []);
                rmdir($sDir);
            }
        }
    }
}
