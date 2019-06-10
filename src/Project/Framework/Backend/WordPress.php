<?php

namespace Shed\Cli\Project\Framework\Backend;

use Shed\Cli\Entity\Option;
use Shed\Cli\Interfaces\Framework;
use Shed\Cli\Project\Framework\Base;

final class WordPress extends Base implements Framework
{
    /**
     * Return the name of the framework
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'WordPress';
    }

    // --------------------------------------------------------------------------

    /**
     * The configurable options for the framework
     *
     * @return Option[]
     */
    public function getOptions(): array
    {
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Returns any ENV vars for the project
     *
     * @param Framework $oFrontendFramework The frontend framework
     *
     * @return array
     */
    public function getEnvVars(Framework $oFrontendFramework): array
    {
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Install the framework
     *
     * @param string    $sPath           The absolute directory to install the framework to
     * @param array     $aOptions        The result of any options
     * @param Framework $oOtherFramework The other framework being installed
     * @param array     $aInstallOptions The install options
     *
     * @return void
     */
    public function install($sPath, array $aOptions, Framework $oOtherFramework, array $aInstallOptions): void
    {
        $this
            ->configureDockerFile($sPath, 'apache-wordpress-php72')
            ->installFramework($sPath, 'apache-wordpress-php72');

        //  Rewrite scripts/build.sh
        file_put_contents(
            $sPath . 'www/scripts/build.sh',
            str_replace(
                'yarn build',
                'yarn production',
                file_get_contents(
                    $sPath . 'www/scripts/build.sh'
                )
            )
        );

        //  Rewrite functions.php
        file_put_contents(
            $sPath . 'www/web/app/themes/custom-theme/functions.php',
            str_replace(
                '/js/theme.min.js',
                '/assets/build/js/app.js',
                file_get_contents(
                    $sPath . 'www/web/app/themes/custom-theme/functions.php'
                )
            )
        );

        //  Rewrite style.css
        file_put_contents(
            $sPath . 'www/web/app/themes/custom-theme/style.css',
            implode("\n", [
                '/*!',
                'Theme Name: custom-theme',
                'Theme URI: https://shedcollective.com',
                'Author: Shed Collective',
                'Author URI: https://shedcollective.com',
                'Description: A custom theme from Shed Collective',
                'Version: 1.0.0',
                '*/',
                '@import url("assets/build/css/app.css");',
            ])
        );

        //  Ensure custom-theme is loaded by default
        file_put_contents(
            $sPath . 'www/config/application.php',
            str_replace(
                'Config::apply();',
                implode("\n", [
                    '// Set custom-theme by default',
                    'Config::define(\'WP_DEFAULT_THEME\', \'custom-theme\');',
                    '',
                    'Config::apply();',
                ]),
                file_get_contents(
                    $sPath . 'www/config/application.php'
                )
            )
        );
    }
}
