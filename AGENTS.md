# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## About

The Shed Command Line Tool is a PHP-based CLI application built with Symfony Console that helps developers at Shed Collective manage projects, servers, and authentication with various cloud providers. The tool is compiled into a standalone PHAR executable using Box.

## Development Commands

### Build
```bash
composer build
```
Compiles the application into a PHAR executable at `dist/shed` using Box. This script ensures PHP 8.1 compatibility by temporarily linking to PHP 8.1 via Homebrew during the build process.

### Test
```bash
composer test
```
Runs PHPUnit test suite from the `tests/` directory.

### Static Analysis
```bash
composer analyse
```
Runs PHPStan static analysis at level 3 across `src/`, `tests/`, and `bin/` directories with unlimited memory.

### Manual Execution
During development, run commands directly via:
```bash
php bin/main.php [command] [options]
```

Or test the compiled PHAR:
```bash
./dist/shed [command] [options]
```

## Architecture

### Application Bootstrap (`bin/main.php`)
- Entry point that initializes Symfony Console Application
- Auto-discovers and registers all command classes from `src/Command/` directory using Symfony Finder
- Loads user configuration from `~/.shedrc/config.json` via `Config::loadConfig()`
- Skips abstract command classes during registration

### Command Structure
All commands extend the base `Shed\Cli\Command` class, which extends Symfony's Command class and provides:
- Standard input/output helpers (`ask()`, `choose()`, `confirm()`)
- Formatted output methods (`banner()`, `keyValueList()`, `error()`, `warning()`)
- Automatic update checking on command execution
- Must implement abstract `go()` method (instead of Symfony's `execute()`)

Commands are organized hierarchically:
- `src/Command/Auth.php` - Base authentication command
  - `Auth/Shed.php` - Shed API authentication
  - `Auth/DigitalOcean.php` - DigitalOcean authentication
  - `Auth/GoogleCloud.php` - Google Cloud authentication
  - `Auth/Amazon.php` - AWS authentication
- `src/Command/Project/` - Project management commands
  - `Project/Create.php` - Interactive project creation
  - `Project/Backup/` - Backup utilities
- `src/Command/Server/` - Server management commands
  - `Server/Create.php` - Server provisioning
  - `Server/Heartbeat.php` - Server health monitoring

### Core Components

#### Entities (`src/Entity/`)
Data models extending `Shed\Cli\Entity` base class with `label` and `slug` properties:
- `Server` - Server representation
- `Provider/*` - Cloud provider resources (Account, Region, Size, Image, Disk)
- `Heartbeat/*` - Server monitoring data (Load, Memory, DiskUsage, Services, Ssl, etc.)

#### Helpers (`src/Helper/`)
Static utility classes:
- `Config` - User configuration persistence to `~/.shedrc/config.json`
- `System` - Shell command execution
- `Directory` - Path manipulation and normalization
- `Updates` - Version checking and update notifications
- `Colors` - Output styling
- `Zip` - Archive operations

#### Services (`src/Service/`)
- `ShedApi` - HTTP client for Shed Collective API at `https://shedcollective.com/api/`

#### Interfaces (`src/Interfaces/`)
Contract definitions (e.g., `Framework` interface for backend/frontend framework implementations)

#### Exceptions (`src/Exceptions/`)
Custom exception hierarchy organized by domain (System, Directory, Environment, Zip, etc.)

### Configuration
User configuration is stored at `~/.shedrc/config.json` and managed through `Helper\Config`:
- Automatically created on first use
- Stores authentication tokens and user preferences
- Persisted as pretty-printed JSON

### Compilation (Box)
`box.json` configuration:
- Main entry: `bin/main.php`
- Includes all `src/` files
- Selectively includes vendor dependencies (excludes test directories, docs, etc.)
- Applies PHP compactor for optimization
- Outputs to `dist/shed`

## PHP Requirements
- **PHP >= 8.1** (enforced during build)
- Extensions: json, zip, curl
- Uses modern PHP features (null coalescing, typed properties, match expressions)

## Dependencies
Key dependencies:
- `symfony/console` 6.* - CLI framework
- `symfony/finder` 6.* - File system iteration
- `symfony/yaml` 6.* - YAML parsing
- `toin0u/digitalocean-v2` - DigitalOcean API
- `google/apiclient` - Google Cloud API
- `aws/aws-sdk-php` - AWS SDK
- `phpseclib/phpseclib` - SSH/crypto operations
- `monolog/monolog` - Logging

Dev dependencies:
- `phpunit/phpunit` 10.*
- `phpstan/phpstan` 2.*

## Testing Notes
- Test bootstrap: `tests/bootstrap.php`
- PHPUnit configured via `phpunit.xml` to run tests with `Test.php` suffix
- Currently minimal test coverage (only bootstrap file present)

## Code Style
- Hungarian notation for variable names (e.g., `$sProjectName`, `$oConfig`, `$aOptions`)
- PSR-4 autoloading with `Shed\Cli\` namespace
- Abstract base classes provide shared functionality
- Static helper classes for utilities
- DocBlocks with type hints on all methods
