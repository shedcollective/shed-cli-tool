# Shed CLI Tool — Agent Reference

## Project Overview

The Shed CLI Tool (`shed`) is a PHP-based command-line application built for Shed Collective team members. Its primary purposes are:

1. **Project scaffolding** — creates new projects with a Docker-based skeleton and installs backend/frontend frameworks
2. **Server provisioning** — creates and configures cloud servers across DigitalOcean, AWS, and Google Cloud
3. **Database and directory backups** — dumps MySQL databases and compresses directories, then uploads to S3-compatible storage
4. **Server health monitoring** — collects and reports system metrics back to the Shed Collective API
5. **Authentication management** — stores and manages API credentials for cloud providers

The tool is compiled into a standalone PHAR executable (`dist/shed`) using [Box](https://github.com/box-project/box) and distributed via Homebrew or Composer. It is installed both on developer machines and on production/staging servers.

---

## Technology Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.1+ |
| CLI framework | Symfony Console 6.x |
| File system | Symfony Finder 6.x |
| YAML parsing | Symfony YAML 6.x |
| Cloud — DigitalOcean | toin0u/digitalocean-v2 5.x |
| Cloud — AWS | aws/aws-sdk-php 3.x |
| Cloud — Google Cloud | google/apiclient 2.x |
| SSH / crypto | phpseclib/phpseclib 3.x |
| HTTP client | kriswallsmith/buzz 1.x |
| Logging | monolog/monolog 3.x |
| Build | humbug/box (PHAR compilation) |
| Testing | phpunit/phpunit 10.x |
| Static analysis | phpstan/phpstan 2.x (level 3) |

**PHP extensions required:** `json`, `zip`, `curl`

---

## Project Structure

```
shed-cli-tool/
├── bin/
│   └── main.php                        # Application entry point
├── src/
│   ├── Command.php                     # Abstract base command
│   ├── Entity.php                      # Abstract base entity
│   ├── Command/
│   │   ├── Auth.php                    # Abstract base for all auth commands
│   │   ├── Auth/
│   │   │   ├── Shed.php                # auth:shed
│   │   │   ├── Amazon.php              # auth:amazon
│   │   │   ├── DigitalOcean.php        # auth:digitalocean
│   │   │   ├── GoogleCloud.php         # auth:googlecloud
│   │   │   └── Backup.php              # auth:backup
│   │   ├── Project/
│   │   │   ├── Create.php              # project:create
│   │   │   └── Backup/
│   │   │       ├── Database.php        # project:backup:database
│   │   │       └── Directories.php     # project:backup:directories
│   │   └── Server/
│   │       ├── Create.php              # server:create
│   │       └── Heartbeat.php           # server:heartbeat
│   ├── Entity/
│   │   ├── Server.php
│   │   ├── Heartbeat.php               # Aggregates all heartbeat metrics
│   │   ├── Heartbeat/                  # Individual metric entities
│   │   │   ├── Load.php, Memory.php, DiskUsage.php
│   │   │   ├── Ssl.php, Services.php, Apt.php
│   │   │   ├── Hostname.php, Ip.php, Os.php
│   │   │   ├── PhpInfo.php, Security.php
│   │   ├── Option.php
│   │   └── Provider/
│   │       ├── Account.php, Region.php, Size.php
│   │       ├── Image.php, Disk.php
│   ├── Helper/
│   │   ├── Config.php                  # ~/.shedrc/config.json persistence
│   │   ├── System.php                  # Shell command execution
│   │   ├── Directory.php               # Path utilities
│   │   ├── Updates.php                 # Version checking
│   │   ├── Colors.php                  # Terminal output styling
│   │   └── Zip.php                     # Archive operations
│   ├── Service/
│   │   └── ShedApi.php                 # Shed Collective API client
│   ├── Project/
│   │   ├── Backup.php                  # Abstract base for backup commands
│   │   └── Framework/
│   │       ├── Base.php                # Abstract framework implementation
│   │       ├── Backend/                # Laravel, Nails, WordPress, None
│   │       └── Frontend/               # Frontend framework options
│   ├── Server/
│   │   ├── Provider.php                # Abstract cloud provider base
│   │   └── Provider/
│   │       ├── DigitalOcean.php
│   │       ├── Amazon.php
│   │       ├── GoogleCloud.php
│   │       └── Api/                    # Provider-specific API wrappers
│   ├── Interfaces/
│   │   ├── Framework.php               # Contract for framework implementations
│   │   └── Provider.php                # Contract for cloud providers
│   ├── Exceptions/                     # Domain-specific exception hierarchy
│   └── Traits/
│       └── Logging.php                 # Logging utility trait
├── tests/                              # PHPUnit test suite
├── dist/
│   └── shed                            # Compiled PHAR executable
├── scripts/
│   ├── build.sh                        # Box compilation script
│   ├── test.sh                         # PHPUnit runner
│   └── analyse.sh                      # PHPStan runner
├── composer.json
├── box.json                            # PHAR compilation config
└── phpunit.xml
```

---

## Architecture

### Application Bootstrap (`bin/main.php`)

1. Sets `memory_limit = -1` (unlimited, required for large operations)
2. Creates a `Symfony\Component\Console\Application` instance
3. Uses Symfony Finder to **auto-discover** all non-abstract command classes under `src/Command/`
4. Calls `Config::loadConfig()` to load (or create) `~/.shedrc/config.json`
5. Registers discovered commands and calls `$app->run()`

### Base Command (`src/Command.php`)

All commands extend `Shed\Cli\Command`, which extends `Symfony\Component\Console\Command\Command`. Key behaviours:

- Wraps Symfony's `execute()` — subclasses implement `go()` instead
- Automatically checks for tool updates before each execution (`Helper\Updates`)
- Provides formatted I/O helpers: `ask()`, `choose()`, `confirm()`, `banner()`, `keyValueList()`, `error()`, `warning()`
- Includes the `Traits\Logging` trait for structured logging via Monolog

### Base Entity (`src/Entity.php`)

Lightweight data carrier with `label` (human-readable) and `slug` (machine identifier) properties. All entities extend this.

### Configuration (`src/Helper/Config.php`)

Manages `~/.shedrc/config.json` (mode `0700`). The JSON structure is:

```json
{
  "auth": {
    "accounts": {
      "shed": { "My Account": "token-value" },
      "digitalocean": { "production": "do-key" },
      "amazon": { "default": "..." },
      "googlecloud": { "default": "..." },
      "backup": { "default": "..." }
    }
  }
}
```

---

## Commands Reference

### Authentication Commands

All auth commands share the same actions: `add`, `view`, `delete`, `help`.

#### `auth:shed`
Manages Shed Collective API tokens.
- `add [--label=<label>] [--token=<token>]` — store a new token interactively or via flags
- `view [--label=<label>|--token=<token>]` — list stored tokens (optionally filtered)
- `delete --label=<label>|--token=<token>` — remove a stored token
- `help` — show how to generate a Shed API token

#### `auth:digitalocean`
Manages DigitalOcean Personal Access Tokens (same actions as above).

#### `auth:amazon`
Manages AWS S3 credentials — stores an Access Key ID (`--label`) and Secret Access Key (`--token`).

#### `auth:googlecloud`
Manages Google Cloud service account JSON credentials.

#### `auth:backup`
Manages S3-compatible backup service credentials (key/secret pair). Used by `project:backup:*` commands.

---

### Project Commands

#### `project:create`
Scaffolds a new project with a Docker skeleton.

| Option | Description |
|---|---|
| `--name` | Human-readable project name |
| `--slug` | Machine slug (used for directory names, Docker service names) |
| `--directory` | Parent directory to create the project in |
| `--backend-framework` | `LARAVEL`, `NAILS`, `WORDPRESS`, or `NONE` |
| `--frontend-framework` | Frontend framework selection |

**What it does:**
1. Downloads the Docker skeleton from Shed Collective
2. Installs the selected backend framework (e.g. `composer create-project laravel/laravel`)
3. Optionally installs a frontend framework
4. Configures `docker-compose.yml` environment variables (project name, slugs, etc.)

#### `project:backup:database`
Dumps one or more MySQL databases and uploads them to S3-compatible storage.

| Option | Description |
|---|---|
| `--domain` | Used to name the backup archive |
| `--s3-key` | S3 access key (or reads from stored `auth:backup` credentials) |
| `--s3-secret` | S3 secret key |
| `--s3-bucket` | S3 bucket name |
| `--mysql-host` | MySQL host (default: `127.0.0.1`) |
| `--mysql-port` | MySQL port (default: `3306`) |
| `--mysql-user` | MySQL user |
| `--mysql-password` | MySQL password |
| `--mysql-database` | Database name(s) — can be specified multiple times |
| `--tmp-dir` | Temporary directory for dump files |

**What it does:** runs `mysqldump`, compresses with zip, uploads to S3 with a timestamped filename, then cleans up temp files.

#### `project:backup:directories`
Backs up file system directories to S3-compatible storage.

Same S3 options as `project:backup:database`, plus the directories to archive. Compresses and uploads with a timestamped filename.

---

### Server Commands

#### `server:create`
Provisions a new cloud server and fully configures it. This is the most complex command.

| Option | Description |
|---|---|
| `--domain` | Primary domain for the server |
| `--hostname` | Server hostname |
| `--environment` | `PRODUCTION` or `STAGING` |
| `--framework` | `NAILS`, `LARAVEL`, `WORDPRESS`, or `NONE` |
| `--provider` | Cloud provider: `DIGITALOCEAN`, `AMAZON`, or `GOOGLECLOUD` |
| `--account` | Named credential account to use |
| `--region` | Provider region slug |
| `--size` | Server size slug |
| `--image` | OS image slug |
| `--keywords` | Keywords to filter images/sizes |
| `--deploy-key` | Public SSH deploy key to install |
| `--ssh-wait` | Seconds to wait for SSH to become available |

**Provisioning workflow:**
1. Validates local environment (composer installed, Shed auth present, backup credentials for production)
2. Interactively collects all required options if not passed as flags
3. Verifies VPN connection (checks public IP against known VPN ranges)
4. Authenticates with cloud provider API and fetches available regions/sizes/images
5. Generates a temporary SSH key pair for provisioning
6. Creates the server via provider API
7. Waits for SSH access (polling with configurable timeout)
8. Over SSH, configures:
   - Deploy key (adds to `~/.ssh/authorized_keys`)
   - MySQL installation and user/database creation (if framework requires it)
   - Scheduled backup cron jobs (production only)
   - SSL certificate (Let's Encrypt / certbot)
   - APT package dependencies
   - Shed CLI tool installation
   - Framework-specific provisioning steps
   - Server reboot
9. Outputs server IP, domain, and MySQL credentials

#### `server:heartbeat`
Collects system metrics and reports them to the Shed Collective API. Designed to be run as a cron job on servers.

**Metrics collected:**
- CPU load averages (1m, 5m, 15m)
- Memory usage (total, used, free)
- Disk usage per mount point
- SSL certificate expiry dates
- Running services status
- Pending APT updates
- OS and kernel information
- Network information (IP addresses)
- Security information

Sends all data to `https://shedcollective.com/api/` as a heartbeat payload.

---

## Key Entities

| Entity | Properties | Purpose |
|---|---|---|
| `Entity\Server` | `id`, `ip`, `domain`, `hostname`, `disk`, `image`, `region`, `size` | Represents a provisioned server |
| `Entity\Heartbeat` | Aggregates 12+ sub-entities | Complete server health snapshot |
| `Entity\Option` | `label`, `type`, validation rules | Interactive CLI option definition |
| `Entity\Provider\Account` | `label`, `token` | Named credential for a cloud provider |
| `Entity\Provider\Region` | `label`, `slug` | Geographic region |
| `Entity\Provider\Size` | `label`, `slug` | Server size/plan |
| `Entity\Provider\Image` | `label`, `slug` | OS image |
| `Entity\Provider\Disk` | `label`, `slug` | Disk type |

**Heartbeat sub-entities:** `Hostname`, `Os`, `Ip`, `Load`, `Memory`, `DiskUsage`, `Services`, `Ssl`, `Apt`, `PhpInfo`, `Security`

---

## External Services

| Service | Usage |
|---|---|
| `https://shedcollective.com/api/` | Token validation (`auth:shed`) and heartbeat reporting |
| `https://ipinfo.io` | Fetches public IP for VPN verification during `server:create` |
| DigitalOcean API | Server creation and management |
| AWS APIs | S3 backups and EC2/Lightsail server creation |
| Google Cloud API | Compute Engine server creation |

---

## Exception Hierarchy

Base: `Shed\Cli\Exceptions\CliException`

Domain-specific exceptions:
- `Auth\AccountNotFoundException`
- `System\CommandFailedException`
- `Directory\FailedToCreateException`
- `Environment\NotValidException`
- `Heartbeat\HeartbeatException`
- `Server\TimeoutException`
- `Server\KeyNotGeneratedException`
- `Zip\CannotOpenException`

---

## Code Conventions

- **Hungarian notation** for variables: `$s` (string), `$o` (object), `$a` (array), `$i` (integer), `$b` (boolean). Example: `$sProjectName`, `$oConfig`, `$aOptions`
- **PSR-4 autoloading** under namespace `Shed\Cli\` → `src/`
- **Abstract base classes** for shared command/entity/provider logic
- **Static helper classes** (`Config`, `System`, `Directory`, `Updates`, `Colors`, `Zip`)
- **DocBlocks** with full `@param` and `@return` type hints on all methods
- Subclasses of `Command` must implement `go()`, not `execute()`

---

## Development Workflows

### Run without building
```bash
php bin/main.php [command] [options]
```

### Build PHAR
```bash
composer build
# outputs dist/shed
```

### Run tests
```bash
composer test
```

### Static analysis
```bash
composer analyse
```

### Install locally (macOS)
```bash
brew tap shedcollective/utilities
brew install shed
```

---

## Adding a New Command

1. Create a class in `src/Command/` extending `Shed\Cli\Command` (or a relevant abstract base)
2. Define the command name via the static `$defaultName` property or `configure()`
3. Implement the `go()` method — this is what runs when the command executes
4. The command is auto-discovered on next run; no registration step required

## Adding a New Cloud Provider

1. Implement `Shed\Cli\Interfaces\Provider` in `src/Server/Provider/YourProvider.php`
2. Extend `Shed\Cli\Server\Provider` (abstract base) for shared provisioning logic
3. Add a corresponding `auth:yourprovider` command in `src/Command/Auth/`
4. Add the provider option to `server:create`'s provider selection list
