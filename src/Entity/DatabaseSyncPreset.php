<?php

namespace Shed\Cli\Entity;

final class DatabaseSyncPreset
{
    public function __construct(
        private readonly string $sLabel,
        private readonly string $sSourceConnection,
        private readonly string $sSourceDatabase,
        private readonly string $sTargetConnection,
        private readonly string $sTargetDatabase,
    ) {}

    public function getLabel(): string            { return $this->sLabel; }
    public function getSourceConnection(): string { return $this->sSourceConnection; }
    public function getSourceDatabase(): string   { return $this->sSourceDatabase; }
    public function getTargetConnection(): string { return $this->sTargetConnection; }
    public function getTargetDatabase(): string   { return $this->sTargetDatabase; }

    public function toArray(): array
    {
        return [
            'source_connection' => $this->sSourceConnection,
            'source_database'   => $this->sSourceDatabase,
            'target_connection' => $this->sTargetConnection,
            'target_database'   => $this->sTargetDatabase,
        ];
    }

    public static function fromArray(string $sLabel, array $aData): self
    {
        return new self(
            sLabel:            $sLabel,
            sSourceConnection: $aData['source_connection'] ?? '',
            sSourceDatabase:   $aData['source_database']   ?? '',
            sTargetConnection: $aData['target_connection'] ?? '',
            sTargetDatabase:   $aData['target_database']   ?? '',
        );
    }
}
