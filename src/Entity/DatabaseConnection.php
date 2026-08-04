<?php

namespace Shed\Cli\Entity;

final class DatabaseConnection
{
    const TYPE_LOCAL  = 'local';
    const TYPE_REMOTE = 'remote';

    const ENV_DEVELOPMENT = 'development';
    const ENV_STAGING     = 'staging';
    const ENV_PRODUCTION  = 'production';

    public function __construct(
        private readonly string $sLabel,
        private readonly string $sType,
        private readonly string $sEnvironment,
        private readonly string $sDbHost,
        private readonly int $iDbPort,
        private readonly string $sDbUser,
        private readonly string $sDbPassword,
        private readonly ?string $sSshHost = null,
        private readonly int $iSshPort = 22,
        private readonly ?string $sSshUser = null,
    ) {
    }

    public function getLabel(): string
    {
        return $this->sLabel;
    }

    public function getType(): string
    {
        return $this->sType;
    }

    public function getEnvironment(): string
    {
        return $this->sEnvironment;
    }

    public function isLocal(): bool
    {
        return $this->sType === self::TYPE_LOCAL;
    }

    public function isRemote(): bool
    {
        return $this->sType === self::TYPE_REMOTE;
    }

    public function isProduction(): bool
    {
        return $this->sEnvironment === self::ENV_PRODUCTION;
    }

    public function getSshHost(): ?string
    {
        return $this->sSshHost;
    }

    public function getSshPort(): int
    {
        return $this->iSshPort;
    }

    public function getSshUser(): ?string
    {
        return $this->sSshUser;
    }

    public function getDbHost(): string
    {
        return $this->sDbHost;
    }

    public function getDbPort(): int
    {
        return $this->iDbPort;
    }

    public function getDbUser(): string
    {
        return $this->sDbUser;
    }

    public function getDbPassword(): string
    {
        return $this->sDbPassword;
    }

    public function toArray(): array
    {
        return [
            'type'        => $this->sType,
            'environment' => $this->sEnvironment,
            'ssh_host'    => $this->sSshHost,
            'ssh_port'    => $this->iSshPort,
            'ssh_user'    => $this->sSshUser,
            'db_host'     => $this->sDbHost,
            'db_port'     => $this->iDbPort,
            'db_user'     => $this->sDbUser,
            'db_password' => $this->sDbPassword,
        ];
    }

    public static function fromArray(string $sLabel, array $aData): self
    {
        return new self(
            sLabel: $sLabel,
            sType: static::normaliseType($aData['type'] ?? null),
            sEnvironment: static::normaliseEnvironment($aData['environment'] ?? null),
            sDbHost: $aData['db_host'] ?? '127.0.0.1',
            iDbPort: (int) ($aData['db_port'] ?? 3306),
            sDbUser: $aData['db_user'] ?? '',
            sDbPassword: $aData['db_password'] ?? '',
            sSshHost: $aData['ssh_host'] ?? null,
            iSshPort: (int) ($aData['ssh_port'] ?? 22),
            sSshUser: $aData['ssh_user'] ?? null,
        );
    }

    private static function normaliseType(mixed $mRaw): string
    {
        return match (true) {
            $mRaw === self::TYPE_REMOTE, $mRaw === 1, $mRaw === '1' => self::TYPE_REMOTE,
            default => self::TYPE_LOCAL,
        };
    }

    private static function normaliseEnvironment(mixed $mRaw): string
    {
        return match ($mRaw) {
            self::ENV_PRODUCTION => self::ENV_PRODUCTION,
            self::ENV_STAGING => self::ENV_STAGING,
            default => self::ENV_DEVELOPMENT,
        };
    }
}
