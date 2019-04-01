<?php

namespace Shed\Cli\Project;

use Shed\Cli\Command;
use Symfony\Component\Console\Input\InputOption;

abstract class Backup extends Command
{
    /**
     * The domain being backed up
     *
     * @var string
     */
    protected $sDomain = '';

    /**
     * The S3 Access Token to use for the backup
     *
     * @var string
     */
    protected $sS3Token = '';

    /**
     * The S3 Access Secret to use for the backup
     *
     * @var string
     */
    protected $sS3Secret = '';

    /**
     * The S3 Bucket to use for the backup
     *
     * @var string
     */
    protected $sS3Bucket = '';

    /**
     * The temporary directory to use
     *
     * @var string
     */
    protected $sTmpDir = '';

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'domain',
                'D',
                InputOption::VALUE_REQUIRED,
                'The domain being backed up'
            )
            ->addOption(
                's3-token',
                't',
                InputOption::VALUE_REQUIRED,
                'The S3 Access Token to use'
            )
            ->addOption(
                's3-secret',
                's',
                InputOption::VALUE_REQUIRED,
                'The S3 Access Secret to use'
            )
            ->addOption(
                's3-bucket',
                'b',
                InputOption::VALUE_REQUIRED,
                'The S3 bucket to use'
            )
            ->addOption(
                'tmp-dir',
                't',
                InputOption::VALUE_OPTIONAL,
                'The temporary directory to use',
                sys_get_temp_dir()
            );
    }
}
