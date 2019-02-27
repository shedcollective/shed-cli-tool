<?php

namespace Shed\Cli\Command\Auth;

use Shed\Cli\Command\Auth;
use Shed\Cli\Entity\Provider\Account;
use Shed\Cli\Helper\Config;
use Shed\Cli\Helper\Debug;
use Shed\Cli\Helper\Directory;
use Shed\Cli\Server\Provider\Api;

final class GoogleCloud extends Auth
{
    /**
     * The third party slug
     *
     * @var string
     */
    const SLUG = 'googlecloud';

    /**
     * The third party name
     *
     * @var string
     */
    const LABEL = 'Google Cloud';

    /**
     * The question for asking the account label
     *
     * @var string
     */
    const QUESTION_TOKEN = 'Key file';

    // --------------------------------------------------------------------------

    /**
     * Show help on how to generate credentials
     */
    protected function help(): void
    {
        //  @todo (Pablo - 2019-02-18) - Write help
    }

    // --------------------------------------------------------------------------

    /**
     * Verify a token is valid
     *
     * @param string $sToken The token to validate
     */
    protected function testToken(string $sToken): void
    {
        Api\GoogleCloud::test($sToken);
    }

    // --------------------------------------------------------------------------

    public static function addAccount(Account $oAccount): void
    {
        $sOldKey = Directory::resolvePath($oAccount->getToken());
        $sNewKey = static::generateKeyPath($oAccount);

        copy($sOldKey, $sNewKey);

        $oAccount->setToken($sNewKey);

        parent::addAccount($oAccount);
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes an account and deletes the key
     *
     * @param Account $oAccount The account to delete
     */
    public static function deleteAccount(Account $oAccount): void
    {
        parent::deleteAccount($oAccount);
        unlink(static::generateKeyPath($oAccount));
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the key directory, creating it if it does not exist
     *
     * @return string
     */
    private static function generateKeyDirectory(): string
    {
        $sKeyDir = Directory::resolve(Config::CONFIG_DIR . 'keys/google-cloud');

        if (!is_dir($sKeyDir)) {
            mkdir($sKeyDir, 0700, true);
        }

        return $sKeyDir;
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the key path for an account
     *
     * @param Account $oAccount The account to generate for
     *
     * @return string
     */
    private static function generateKeyPath(Account $oAccount): string
    {
        return Directory::resolvePath(
            static::generateKeyDirectory() . $oAccount->getLabel() . '.json'
        );
    }
}
