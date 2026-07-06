<?php

namespace Shed\Cli\Command\Database;

use Shed\Cli\Command;
use Shed\Cli\Entity\DatabaseConnection;
use Shed\Cli\Helper\Config;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\Question;

final class Connection extends Command
{
    const CONFIG_KEY = 'database.connections';

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this
            ->setName('db:connection')
            ->setDescription('Manage saved database connections')
            ->setHelp('Add, view, or delete database connections used by database:sync.')
            ->addArgument(
                'action',
                InputArgument::OPTIONAL,
                '[add] a new connection, [view] or [delete] existing connections'
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
        switch ($this->oInput->getArgument('action')) {
            case 'add':
                $this->add();
                break;
            case 'delete':
                $this->delete();
                break;
            default:
                $this->view();
                break;
        }

        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    /**
     * List all saved connections in a compact table
     */
    private function view(): void
    {
        $aConnections = static::getConnections();

        if (empty($aConnections)) {
            $this->oOutput->writeln('');
            $this->oOutput->writeln('No connections saved. Run <info>shed db:connection add</info> to configure one.');
            $this->oOutput->writeln('');
            return;
        }

        $this->oOutput->writeln('');

        $oTable = new Table($this->oOutput);
        $oTable->setHeaders(['Label', 'Environment', 'Type', 'SSH Host', 'MySQL']);

        foreach ($aConnections as $oConn) {
            $sSsh = $oConn->isRemote()
                ? ($oConn->getSshUser() ? $oConn->getSshUser() . '@' : '') . $oConn->getSshHost()
                . ($oConn->getSshPort() !== 22 ? ':' . $oConn->getSshPort() : '')
                : '-';

            $sEnv = match ($oConn->getEnvironment()) {
                DatabaseConnection::ENV_PRODUCTION => '<error>production</error>',
                DatabaseConnection::ENV_STAGING => '<comment>staging</comment>',
                default => 'development',
            };

            $oTable->addRow([
                $oConn->getLabel(),
                $sEnv,
                $oConn->getType(),
                $sSsh,
                $oConn->getDbHost() . ':' . $oConn->getDbPort() . ' / ' . $oConn->getDbUser(),
            ]);
        }

        $oTable->render();
        $this->oOutput->writeln('');
    }

    // --------------------------------------------------------------------------

    /**
     * Add a new connection
     */
    private function add(): void
    {
        $this->banner('Add a database connection');

        $oConn = $this->runWizard();

        if ($oConn === null) {
            return;
        }

        $this->oOutput->writeln('Does this all look OK?');
        $this->keyValueList($this->describeConnection($oConn));

        if ($this->confirm('Save connection?')) {
            static::saveConnection($oConn);
            $this->oOutput->writeln('');
            $this->oOutput->writeln('Connection <info>' . $oConn->getLabel() . '</info> saved.');
            $this->oOutput->writeln('');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Delete an existing connection
     */
    private function delete(): void
    {
        $aConnections = static::getConnections();

        if (empty($aConnections)) {
            $this->oOutput->writeln('');
            $this->oOutput->writeln('No connections saved.');
            $this->oOutput->writeln('');
            return;
        }

        $aConnList = array_values($aConnections);
        $aChoices  = array_map(
            fn($o) => sprintf('%s [%s / %s]', $o->getLabel(), $o->getEnvironment(), $o->getType()),
            $aConnList
        );
        $iIndex    = (int) $this->choose('Which connection to delete?', $aChoices);
        $oConn     = $aConnList[$iIndex];

        $this->oOutput->writeln('You are about to delete:');
        $this->keyValueList($this->describeConnection($oConn));

        if ($this->confirm('Delete this connection?')) {
            static::deleteConnection($oConn);
            $this->oOutput->writeln('');
            $this->oOutput->writeln('Connection <info>' . $oConn->getLabel() . '</info> deleted.');
            $this->oOutput->writeln('');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Interactive wizard to collect and verify connection details.
     * Returns null if the user cancels after a failed connection test.
     *
     * @return DatabaseConnection|null
     */
    public function runWizard(): ?DatabaseConnection
    {
        $sLabel = $this->ask('Connection label:', null, function ($sValue) {
            if (empty(trim($sValue ?? ''))) {
                $this->error(['Label is required']);
                return false;
            }
            if (static::getConnectionByLabel($sValue) !== null) {
                $this->error(['A connection with that label already exists']);
                return false;
            }
            return true;
        });

        $iTypeChoice = $this->choose('Connection type:', [
            'Local (direct MySQL connection)',
            'Remote (connect via SSH)',
        ]);
        $sType       = (int) $iTypeChoice === 1
            ? DatabaseConnection::TYPE_REMOTE
            : DatabaseConnection::TYPE_LOCAL;

        $iEnvChoice   = $this->choose('Environment:', [
            'Development',
            'Staging',
            'Production',
        ]);
        $sEnvironment = match ((int) $iEnvChoice) {
            1 => DatabaseConnection::ENV_STAGING,
            2 => DatabaseConnection::ENV_PRODUCTION,
            default => DatabaseConnection::ENV_DEVELOPMENT,
        };

        $sSshHost = null;
        $iSshPort = 22;
        $sSshUser = null;

        if ($sType === DatabaseConnection::TYPE_REMOTE) {
            $sSshHost    = $this->ask('SSH host (e.g. prod.example.com):');
            $iSshPort    = (int) ($this->ask('SSH port:', '22') ?: 22);
            $sSshUserRaw = $this->ask('SSH user (leave blank to use ~/.ssh/config default):');
            $sSshUser    = empty(trim($sSshUserRaw ?? '')) ? null : trim($sSshUserRaw);
        }

        $sDbHost     = $this->ask('MySQL host:', '127.0.0.1') ?: '127.0.0.1';
        $iDbPort     = (int) ($this->ask('MySQL port:', '3306') ?: 3306);
        $sDbUser     = $this->ask('MySQL user:') ?? '';
        $sDbPassword = $this->askPassword('MySQL password:');

        $oConn = new DatabaseConnection(
            sLabel: $sLabel ?? '',
            sType: $sType,
            sEnvironment: $sEnvironment,
            sDbHost: $sDbHost,
            iDbPort: $iDbPort,
            sDbUser: $sDbUser,
            sDbPassword: $sDbPassword,
            sSshHost: $sSshHost,
            iSshPort: $iSshPort,
            sSshUser: $sSshUser,
        );

        $this->oOutput->writeln('');
        $this->oOutput->write('Testing connection... ');
        $sError = $this->testConnection($oConn);

        if ($sError === null) {
            $this->oOutput->writeln('<info>OK</info>');
        } else {
            $this->oOutput->writeln('<error>failed</error>');
            $this->warning(['Connection test failed:', $sError]);

            if (!$this->confirm('Save anyway?', false)) {
                $this->oOutput->writeln('Connection not saved.');
                $this->oOutput->writeln('');
                return null;
            }
        }

        return $oConn;
    }

    // --------------------------------------------------------------------------

    /**
     * Test SSH (if remote) then MySQL connectivity for the given connection.
     * Returns null on success, or an error message string on failure.
     *
     * @param DatabaseConnection $oConn
     *
     * @return string|null
     */
    private function testConnection(DatabaseConnection $oConn): ?string
    {
        if ($oConn->isRemote()) {
            $sSshTarget = $oConn->getSshUser()
                ? $oConn->getSshUser() . '@' . $oConn->getSshHost()
                : (string) $oConn->getSshHost();
            $sPortFlag  = $oConn->getSshPort() !== 22 ? '-p ' . $oConn->getSshPort() . ' ' : '';

            $sCmd = sprintf(
                'ssh %s-o ConnectTimeout=10 -o BatchMode=yes %s exit 2>&1',
                $sPortFlag,
                escapeshellarg($sSshTarget)
            );
            exec($sCmd, $aOut, $iCode);

            if ($iCode !== 0) {
                return 'SSH: ' . (implode(' ', $aOut) ?: 'Could not connect to ' . $sSshTarget);
            }
        }

        //  Write a temporary credentials file
        $sTmpDir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shed_test_' . bin2hex(random_bytes(4));
        $sTmpFile = $sTmpDir . DIRECTORY_SEPARATOR . 'my.cnf';
        mkdir($sTmpDir, 0700, true);
        file_put_contents($sTmpFile, '[client]' . PHP_EOL . 'password=' . $oConn->getDbPassword() . PHP_EOL);
        chmod($sTmpFile, 0600);

        try {
            if ($oConn->isLocal()) {
                $sCmd = sprintf(
                    'mysql --defaults-extra-file=%s -h %s -P %d -u %s -e "SELECT 1" 2>&1',
                    escapeshellarg($sTmpFile),
                    escapeshellarg($oConn->getDbHost()),
                    $oConn->getDbPort(),
                    escapeshellarg($oConn->getDbUser())
                );
                exec($sCmd, $aOut, $iCode);
            } else {
                $sRemoteCmd = sprintf(
                    'MYSQL_PWD=%s mysql -h %s -P %d -u %s -e %s',
                    escapeshellarg($oConn->getDbPassword()),
                    escapeshellarg($oConn->getDbHost()),
                    $oConn->getDbPort(),
                    escapeshellarg($oConn->getDbUser()),
                    escapeshellarg('SELECT 1')
                );
                $sSshTarget = $oConn->getSshUser()
                    ? $oConn->getSshUser() . '@' . $oConn->getSshHost()
                    : (string) $oConn->getSshHost();
                $sPortFlag  = $oConn->getSshPort() !== 22 ? '-p ' . $oConn->getSshPort() . ' ' : '';
                $sCmd       = sprintf(
                    'ssh %s%s %s 2>&1',
                    $sPortFlag,
                    escapeshellarg($sSshTarget),
                    escapeshellarg($sRemoteCmd)
                );
                exec($sCmd, $aOut, $iCode);
            }

            if ($iCode !== 0) {
                $sRaw = trim(implode(' ', $aOut));
                return 'MySQL: ' . ($sRaw ?: 'Could not connect');
            }

        } finally {
            @unlink($sTmpFile);
            @rmdir($sTmpDir);
        }

        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * Ask for a password with hidden input
     *
     * @param string $sPrompt
     *
     * @return string
     */
    public function askPassword(string $sPrompt): string
    {
        $oHelper   = $this->getHelper('question');
        $oQuestion = new Question(trim($sPrompt));
        $oQuestion->setHidden(true);
        $oQuestion->setHiddenFallback(false);

        return trim($oHelper->ask($this->oInput, $this->oOutput, $oQuestion) ?? '');
    }

    // --------------------------------------------------------------------------

    /**
     * Build a human-readable summary array for a connection
     *
     * @param DatabaseConnection $oConn
     *
     * @return array
     */
    public function describeConnection(DatabaseConnection $oConn): array
    {
        $sEnvDisplay = match ($oConn->getEnvironment()) {
            DatabaseConnection::ENV_PRODUCTION => '<error>production</error>',
            DatabaseConnection::ENV_STAGING => '<comment>staging</comment>',
            default => 'development',
        };

        $aDetails = [
            'Label'       => $oConn->getLabel(),
            'Environment' => $sEnvDisplay,
            'Type'        => $oConn->getType(),
            'DB Host'     => $oConn->getDbHost() . ':' . $oConn->getDbPort(),
            'DB User'     => $oConn->getDbUser(),
            'Password'    => $oConn->getDbPassword() ? '<info>set</info>' : '<comment>not set</comment>',
        ];

        if ($oConn->isRemote()) {
            $sSshTarget = $oConn->getSshUser()
                ? $oConn->getSshUser() . '@' . $oConn->getSshHost()
                : (string) $oConn->getSshHost();
            if ($oConn->getSshPort() !== 22) {
                $sSshTarget .= ':' . $oConn->getSshPort();
            }
            $aDetails['SSH Host'] = $sSshTarget;
        }

        return $aDetails;
    }

    // --------------------------------------------------------------------------

    /**
     * Return all saved connections keyed by label
     *
     * @return DatabaseConnection[]
     */
    public static function getConnections(): array
    {
        $aRaw = (array) (Config::get(static::CONFIG_KEY) ?? []);
        $aOut = [];
        foreach ($aRaw as $sLabel => $aData) {
            $aOut[$sLabel] = DatabaseConnection::fromArray($sLabel, (array) $aData);
        }
        return $aOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Return a connection by label, or null if not found
     *
     * @param string $sLabel
     *
     * @return DatabaseConnection|null
     */
    public static function getConnectionByLabel(string $sLabel): ?DatabaseConnection
    {
        return static::getConnections()[$sLabel] ?? null;
    }

    // --------------------------------------------------------------------------

    /**
     * Persist a connection to config
     *
     * @param DatabaseConnection $oConn
     */
    public static function saveConnection(DatabaseConnection $oConn): void
    {
        $aConfig = [];
        foreach (static::getConnections() as $oExisting) {
            $aConfig[$oExisting->getLabel()] = $oExisting->toArray();
        }
        $aConfig[$oConn->getLabel()] = $oConn->toArray();
        ksort($aConfig);
        Config::set(static::CONFIG_KEY, $aConfig);
    }

    // --------------------------------------------------------------------------

    /**
     * Remove a connection from config
     *
     * @param DatabaseConnection $oConn
     */
    public static function deleteConnection(DatabaseConnection $oConn): void
    {
        $aConfig = [];
        foreach (static::getConnections() as $oExisting) {
            if ($oExisting->getLabel() !== $oConn->getLabel()) {
                $aConfig[$oExisting->getLabel()] = $oExisting->toArray();
            }
        }
        Config::set(static::CONFIG_KEY, $aConfig);
    }
}
