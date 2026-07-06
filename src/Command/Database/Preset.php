<?php

namespace Shed\Cli\Command\Database;

use Shed\Cli\Entity\DatabaseSyncPreset;
use Shed\Cli\Helper\Config;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;

final class Preset extends DatabaseCommand
{
    const CONFIG_KEY = 'database.presets';

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this
            ->setName('db:preset')
            ->setDescription('Manage database sync presets')
            ->setHelp('Save named source→target pairs to quickly repeat common syncs. Run with: shed db:sync {preset-name}')
            ->addArgument(
                'action',
                InputArgument::OPTIONAL,
                '[add] a new preset, [view] or [delete] existing presets'
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
        try {

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

        } finally {
            $this->cleanup();
        }

        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    /**
     * List all saved presets in a compact table
     */
    private function view(): void
    {
        $aPresets = static::getPresets();

        if (empty($aPresets)) {
            $this->oOutput->writeln('');
            $this->oOutput->writeln('No presets saved. Run <info>shed db:preset add</info> to create one.');
            $this->oOutput->writeln('');
            return;
        }

        $this->oOutput->writeln('');

        $oTable = new Table($this->oOutput);
        $oTable->setHeaders(['Preset', 'Source connection', 'Source DB', 'Target connection', 'Target DB']);

        foreach ($aPresets as $oPreset) {
            $oTable->addRow([
                $oPreset->getLabel(),
                $oPreset->getSourceConnection(),
                $oPreset->getSourceDatabase(),
                $oPreset->getTargetConnection(),
                $oPreset->getTargetDatabase(),
            ]);
        }

        $oTable->render();
        $this->oOutput->writeln('');
        $this->oOutput->writeln('Run a preset with: <info>shed db:sync {preset-name}</info>');
        $this->oOutput->writeln('');
    }

    // --------------------------------------------------------------------------

    /**
     * Interactively create a new preset
     */
    private function add(): void
    {
        $this->banner('Create a sync preset');

        $sLabel = $this->ask('Preset name (used as CLI argument, e.g. prod-to-local):', null, function ($sValue) {
            if (empty(trim($sValue ?? ''))) {
                $this->error(['Preset name is required']);
                return false;
            }
            if (static::getPresetByLabel($sValue) !== null) {
                $this->error(['A preset with that name already exists']);
                return false;
            }
            return true;
        });

        //  Source
        $this->oOutput->writeln('<comment>Source: the database you will sync FROM</comment>');
        $oSourceConn = $this->pickConnection('Source', preferLocal: false, bExcludeProduction: false);

        $this->oOutput->write('↳ Fetching databases... ');
        $aSourceDbs = $this->listDatabases($oSourceConn);
        $this->oOutput->writeln('<info>done</info>');

        if (empty($aSourceDbs)) {
            $this->error(['No accessible databases found on the source connection.']);
            return;
        }

        $iSourceIdx = (int) $this->choose('Source database:', $aSourceDbs);
        $sSourceDb  = $aSourceDbs[$iSourceIdx];

        //  Target
        $this->oOutput->writeln('');
        $this->oOutput->writeln('<comment>Target: the database you will sync INTO</comment>');
        $oTargetConn = $this->pickConnection('Target', preferLocal: true, bExcludeProduction: true);

        $this->oOutput->write('↳ Fetching databases... ');
        $aTargetDbs = $this->listDatabases($oTargetConn);
        $this->oOutput->writeln('<info>done</info>');

        $aDbChoices   = $aTargetDbs;
        $aDbChoices[] = 'Create a new database';
        $iTargetIdx   = (int) $this->choose('Target database:', $aDbChoices);

        if ($iTargetIdx === count($aTargetDbs)) {
            $sTargetDb = $this->ask('New database name:') ?? '';
        } else {
            $sTargetDb = $aTargetDbs[$iTargetIdx];
        }

        if ($oSourceConn->getLabel() === $oTargetConn->getLabel() && $sSourceDb === $sTargetDb) {
            $this->error(['Source and target cannot be the same database.']);
            return;
        }

        $oPreset = new DatabaseSyncPreset(
            sLabel:            $sLabel ?? '',
            sSourceConnection: $oSourceConn->getLabel(),
            sSourceDatabase:   $sSourceDb,
            sTargetConnection: $oTargetConn->getLabel(),
            sTargetDatabase:   $sTargetDb,
        );

        $this->keyValueList([
            'Preset name'       => $oPreset->getLabel(),
            'Source connection' => $oPreset->getSourceConnection(),
            'Source database'   => $oPreset->getSourceDatabase(),
            'Target connection' => $oPreset->getTargetConnection(),
            'Target database'   => $oPreset->getTargetDatabase(),
            'Run with'          => 'shed db:sync ' . $oPreset->getLabel(),
        ], 'Does this look correct?');

        if ($this->confirm('Save preset?')) {
            static::savePreset($oPreset);
            $this->oOutput->writeln('');
            $this->oOutput->writeln('Preset <info>' . $oPreset->getLabel() . '</info> saved.');
            $this->oOutput->writeln('Run with: <info>shed db:sync ' . $oPreset->getLabel() . '</info>');
            $this->oOutput->writeln('');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Delete a saved preset
     */
    private function delete(): void
    {
        $aPresets = static::getPresets();

        if (empty($aPresets)) {
            $this->oOutput->writeln('');
            $this->oOutput->writeln('No presets saved.');
            $this->oOutput->writeln('');
            return;
        }

        $aPresetList = array_values($aPresets);
        $aChoices    = array_map(
            fn($o) => sprintf(
                '%s  (%s/%s → %s/%s)',
                $o->getLabel(),
                $o->getSourceConnection(), $o->getSourceDatabase(),
                $o->getTargetConnection(), $o->getTargetDatabase()
            ),
            $aPresetList
        );

        $iIndex  = (int) $this->choose('Which preset to delete?', $aChoices);
        $oPreset = $aPresetList[$iIndex];

        $this->keyValueList([
            'Preset name'       => $oPreset->getLabel(),
            'Source connection' => $oPreset->getSourceConnection(),
            'Source database'   => $oPreset->getSourceDatabase(),
            'Target connection' => $oPreset->getTargetConnection(),
            'Target database'   => $oPreset->getTargetDatabase(),
        ], 'You are about to delete:');

        if ($this->confirm('Delete this preset?')) {
            static::deletePreset($oPreset);
            $this->oOutput->writeln('');
            $this->oOutput->writeln('Preset <info>' . $oPreset->getLabel() . '</info> deleted.');
            $this->oOutput->writeln('');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Return all saved presets keyed by label
     *
     * @return DatabaseSyncPreset[]
     */
    public static function getPresets(): array
    {
        $aRaw = (array) (Config::get(static::CONFIG_KEY) ?? []);
        $aOut = [];
        foreach ($aRaw as $sLabel => $aData) {
            $aOut[$sLabel] = DatabaseSyncPreset::fromArray($sLabel, (array) $aData);
        }
        return $aOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Return a preset by label, or null if not found
     *
     * @param string $sLabel
     *
     * @return DatabaseSyncPreset|null
     */
    public static function getPresetByLabel(string $sLabel): ?DatabaseSyncPreset
    {
        return static::getPresets()[$sLabel] ?? null;
    }

    // --------------------------------------------------------------------------

    /**
     * Persist a preset to config
     *
     * @param DatabaseSyncPreset $oPreset
     */
    public static function savePreset(DatabaseSyncPreset $oPreset): void
    {
        $aConfig = [];
        foreach (static::getPresets() as $oExisting) {
            $aConfig[$oExisting->getLabel()] = $oExisting->toArray();
        }
        $aConfig[$oPreset->getLabel()] = $oPreset->toArray();
        ksort($aConfig);
        Config::set(static::CONFIG_KEY, $aConfig);
    }

    // --------------------------------------------------------------------------

    /**
     * Remove a preset from config
     *
     * @param DatabaseSyncPreset $oPreset
     */
    public static function deletePreset(DatabaseSyncPreset $oPreset): void
    {
        $aConfig = [];
        foreach (static::getPresets() as $oExisting) {
            if ($oExisting->getLabel() !== $oPreset->getLabel()) {
                $aConfig[$oExisting->getLabel()] = $oExisting->toArray();
            }
        }
        Config::set(static::CONFIG_KEY, $aConfig);
    }
}
