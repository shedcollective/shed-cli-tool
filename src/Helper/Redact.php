<?php

namespace Shed\Cli\Helper;

/**
 * Class Redact
 *
 * @package Shed\Cli\Helper
 */
final class Redact
{
    /**
     * Patterns for credentials which commonly appear inline in a command
     *
     * @var array
     */
    private const SECRETS = [
        //  scheme://user:password@host
        '/([a-zA-Z][a-zA-Z0-9+.\-]*:\/\/[^:\/\s]+:)([^@\s]+)(?=@)/',
        //  --password=secret, --password secret
        '/(--(?:password|pass|secret|token|api-key)[=\s]+)(\S+)/i',
        //  MYSQL_PASSWORD=secret, S3_ACCESS_SECRET=secret, API_TOKEN=secret
        '/(\b\w*(?:PASS|PASSWD|PASSWORD|SECRET|TOKEN|API_KEY|ACCESS_KEY|CREDENTIAL)\w*\s*=\s*)(\S+)/i',
        //  Authorization headers
        '/((?:Bearer|Basic)\s+)([A-Za-z0-9._\-=+\/]+)/i',
    ];

    /**
     * Attaching the password to `-p` is a MySQL family idiom, so the pattern is
     * only applied to commands which invoke one of its clients. Matching it
     * everywhere mangles unrelated arguments — `run-parts`, which appears in
     * /etc/crontab on a stock Ubuntu install, becomes `run-p[REDACTED]`.
     *
     * The leading (?<![\w\-]) keeps it to a standalone argument, so neither
     * `--password=x` nor a hyphenated word is touched, and a bare `-p` survives.
     *
     * @var string
     */
    private const SECRET_MYSQL_PASSWORD = '/(?<![\w\-])(-p)(?=\S)(\S+)/';

    /**
     * The MySQL clients which accept a password attached to -p
     *
     * @var string
     */
    private const MYSQL_CLIENTS = '/\bmysql(?:dump|admin|show|check|import|_upgrade)?\b/i';

    // --------------------------------------------------------------------------

    /**
     * Masks credentials which have been written inline into a command
     *
     * The heartbeat is sent off the server, so anything resembling a secret is
     * replaced whilst leaving the surrounding flag intact, keeping the shape of
     * the command legible.
     *
     * @param string $sValue The value to redact
     *
     * @return string
     */
    public static function secrets(string $sValue): string
    {
        foreach (self::SECRETS as $sPattern) {
            $sValue = preg_replace($sPattern, '$1[REDACTED]', $sValue) ?? $sValue;
        }

        if (preg_match(self::MYSQL_CLIENTS, $sValue)) {
            $sValue = preg_replace(self::SECRET_MYSQL_PASSWORD, '$1[REDACTED]', $sValue) ?? $sValue;
        }

        return $sValue;
    }
}
