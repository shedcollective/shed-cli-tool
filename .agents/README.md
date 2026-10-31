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
│   │   │   ├── Hostname.php, Ip.php, IpAddresses.php, Os.php
│   │   │   ├── PhpInfo.php, Security.php
│   │   │   ├── Version.php, Cron.php, Node.php, Mysql.php, Sudoers.php, Accounts.php, Docker.php, Elasticsearch.php
│   │   │   ├── Containers.php          # Per-container runtime inventory
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
│   │   ├── Redact.php                  # Masks credentials in reported commands
│   │   ├── Containers.php              # Container detection, docker exec, time budgets
│   │   ├── Trust.php                   # Root-owned-path check before executing a binary
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
   - MySQL installation and user/database creation (images whose label contains `mysql` or `lamp`; the user gets the server default auth plugin, `caching_sha2_password` on MySQL 8+, and the image script reports a failed statement as `{"error": …}` instead of credentials)
   - Scheduled backup cron jobs (production only)
   - Apache `deploy.conf` `ServerName` / `ServerAlias` from the intended domain (lamp and webserver images; required so the catch-all default vhost does not take real-site traffic)
   - PHP `display_errors` in `/etc/php/99-shed-php.ini` (staging only; images ship with it `Off`, and each SAPI's `conf.d` links to this file, so one edit covers CLI and FPM). php-fpm is reloaded so the change is live before the final reboot
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
- Network information (IP addresses). `ip` is a public IPv4 when one is known: first public
  address already on a guest NIC, else (on GCE / EC2 only, when the NIC is all private) the
  instance-metadata external IP, else the first interface address. `ip_addresses` lists every
  known address with `scope` (`external` / `private`) and `source` (`interface`,
  `gcp-metadata`, `ec2-metadata`). IPv6 is listed but not chosen as `ip` while an IPv4 exists.
  Metadata probes use a 2s timeout and must not fail the heartbeat. ipinfo.io is not used.
- Security information
- Shed CLI tool version (from the server's `composer.json`)
- Configured cron jobs for all users, plus `/etc/crontab`, `/etc/cron.d`, the
  `run-parts` directories, and the cron daemon's status. Requires root to
  enumerate per-user crontabs; degrades to an `error` key otherwise. Credentials
  written inline into a job (`-pSECRET`, `--password=`, `Bearer …`,
  `*_SECRET=`) are redacted before the payload leaves the server.
- MySQL / MariaDB server version, when a server binary is installed. The engine
  is taken from the `--version` banner (`MariaDB` vs not), never from the
  filename — Ubuntu's MariaDB still ships a `mysqld` symlink. The binary is
  only executed when it is owned by root down every path component. A missing
  database is `present: false`; macOS reports the whole block as `null`.
  See "MySQL version reporting" below.
- Node installations and the versions actually in use, in three parts:
  `system` (the install a shell resolves, plus any others shadowing it),
  `users` (what each user has installed for themselves, via nvm or another
  version manager, and which version their `default` alias currently selects),
  and `running` (every node process, the user running it and the version it was
  loaded from, read out of `/proc`). See "Node version reporting" below.
- Docker Engine, when installed: the version the running daemon reports (else
  `dockerd --version`, else dpkg), the dpkg package, the `docker` unit's systemd
  state, and running / total container counts. A host with Docker installed may
  run its application in containers, so the other versions in the heartbeat
  describe the host rather than necessarily the application. Counts are `null`
  when the daemon cannot be asked — unknown, never zero. See "Docker reporting"
  below.
- On a Docker host, the runtimes inside each running container (base OS, PHP, PHP-FPM, Node, npm,
  MySQL, MariaDB, Postgres, Redis, Caddy, nginx, Apache), reported **in addition to** the host's own
  versions, and node processes attributed to the container they run in, with the version asked of
  the container. Detected automatically. See "Container reporting" below.
- Elasticsearch, when installed: its version (from dpkg, else the versioned
  server jar under `/usr/share/elasticsearch/lib`) and the `elasticsearch`
  unit's systemd state. Nothing is executed and the HTTP port is never queried.
  See "Elasticsearch reporting" below.
- Who has sudo access and what they may run: the privilege specifications in
  `/etc/sudoers` and every file in `/etc/sudoers.d`, plus the resolved current
  membership of any group one of those lines grants access to (a `%group` line
  names a group, not the people in it). Requires root to read `/etc/sudoers`
  (mode `0440`) and list `/etc/sudoers.d` (mode `0750`); degrades to an `error`
  key per file otherwise, the same as the cron block. Commands are redacted the
  same way a cron job's command is. See "Sudo access reporting" below.
- The accounts on the host and the SSH keys each one authorises: every entry in
  the user database with its groups, shell and password state (`set` / `locked`
  / `none` — never the hash), and for every key in the files sshd reads for that
  account its type, size, SHA256 fingerprint, comment and options. **Key material
  never leaves the server**, only the fingerprint. sshd's effective
  `PermitRootLogin`, `PubkeyAuthentication`, `PasswordAuthentication`,
  `AuthorizedKeysFile`, `AuthorizedKeysCommand` and Allow/Deny lists are
  reported alongside. Requires root; degrades to an `error` per account or file
  otherwise. See "Account and SSH key reporting" below.

Sends all data to `https://api.ne.at/v1/server/heartbeat` as a heartbeat payload.

| Option | Description |
|---|---|
| `--dry-run` | Print the payload as JSON instead of sending it. Skips the jitter wait. |

When stdin is not a terminal (the hourly cron), or when run with
`--no-interaction` / `--quiet`, the command sleeps a stable
`crc32(hostname) % 600` seconds before collecting, so the fleet's :00 burst
becomes a trickle across ten minutes. A terminal session skips the wait.
Symfony Console 6 leaves `isInteractive()` true unless one of those flags is
passed, and the cron line passes neither, so the wait is decided from the
terminal itself (`stream_isatty`, which does not need the posix extension).

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

**Heartbeat sub-entities:** `Hostname`, `Os`, `Ip`, `IpAddresses`, `Load`, `Memory`, `DiskUsage`, `Docker`, `Containers`, `Elasticsearch`, `Services`, `Ssl`, `Apt`, `PhpInfo`, `Security`, `Version`, `Cron`, `Node`, `Mysql`, `Sudoers`, `Accounts`

---

## Node Version Reporting

`Entity\Heartbeat\Node` answers three separate questions, because on a host
where node is managed per user the answers differ:

| Key | What it reports |
|---|---|
| `system` | Node installed outside any home directory — what `node` resolves to on the PATH, plus every other system binary found, its dpkg package where it has one, and the npm beside it. A tarball unpacked into `/usr/local/bin` shadows the packaged `/usr/bin/node` without removing it, so the full `installs` list is what shows that has happened. |
| `users` | Per-user installs, keyed by username. nvm is reported in detail: the versions installed, the aliases defined, the `default` alias and the version it currently resolves to, whether that version is actually installed, and whether the user's shell loads nvm at all. fnm, volta, asdf, mise, nodenv and `n` are detected too, but without the alias detail. |
| `running` | Every node process, with the user running it and the version it was loaded from. `by_user` summarises this as user → version → count and is computed over every process, so it stays accurate even when the `processes` detail is truncated. |

### How a version is established

`running` reads `/proc/<pid>/exe`, so a process is attributed to the binary its
kernel image was loaded from, whatever the PATH or a process manager's
configuration might imply. Reading that link for another user's process needs
`CAP_SYS_PTRACE` — root holds it on a normal host, but it is dropped by default
inside a container. Where it is missing the process is identified from `comm`
and `cmdline` instead, and anything that cannot be identified either way is
counted in `unreadable` so that a partial view is never mistaken for a quiet
one.

Each version carries a `version_source`:

- `binary` — node was asked, and this is its answer.
- `path` — taken from the directory the install sits in (`.nvm/versions/node/v20.11.1/…`).

The distinction matters because the heartbeat runs as root and **will not
execute a binary that is not owned by root down every component of its path**.
A node in a user's home directory is a file that user controls; running it to
ask its version would hand them root. Those installs are reported from their
path instead, which is a claim by whoever owns the directory rather than by
node — hence the flag.

For the same reason, files read out of a home directory (nvm aliases, shell
profiles) must resolve to somewhere still inside that home and belong to either
that user or root, so a symlink planted in a home cannot be used to post the
contents of an arbitrary root-only file to the API.

A process inside a container is a special case — see "Container reporting" below. Its `exe`
link names a file in the container's filesystem, so resolving it on the host finds nothing, or
finds the host's own node and reports *that* version. Such a process carries `manager: "container"`, a `container` object, and a version from `container-exec`.

Process arguments are where an application is identified — the binary is `node`
on all of them — but also where a careless deploy leaves a token or a database
password, so they are passed through `Helper\Redact` before they leave the
server, exactly as cron commands are.

---

## Container reporting

On a Docker host the application's real runtime lives in its containers, and the host's `php`,
`mysql` and `node` blocks do not describe it. `Entity\Heartbeat\Containers` (the `containers` block)
and the container branch of `Entity\Heartbeat\Node` fix that. Both share `Helper\Containers`.

### Always on

There is no switch. Containers are detected wherever they are, and reported **beside** the host's
own `php`, `mysql` and `node` blocks, which are unchanged. On a host without containers this costs
nothing: a process's container is read from its cgroup, Docker is only asked anything once a
container process turns up, and the `containers` block is `null` when Docker is not installed.

See what a host would send with `shed server:heartbeat --dry-run`, which prints the payload instead
of sending it.

### Only through the Docker API — never from the host

The heartbeat runs as root, and a container's filesystem belongs to whoever built its image.
Running a container binary from the host — through `/proc/<pid>/root`, or with `nsenter`, which
keeps root's capabilities and skips the container's seccomp/AppArmor profile — would run that
image's code as host root, and an absolute symlink read through `/proc/<pid>/root` resolves against
the *host's* root. So every command goes through `docker exec` (inside the container's own
isolation, as its own user) and files through `docker cp -L` (resolved inside the container).
`version_source` is therefore always `container-exec`; the brief's `nsenter` and `proc-root`
sources are deliberately not used. The client is run under `env -i` (so `DOCKER_HOST` cannot
redirect it) and `timeout`, and only when it is root-owned (`Helper\Trust`). Output read back is
capped at 64 KB per call.

### Which container a process is in

`/proc/<pid>/cgroup` (world-readable, no capability needed): `docker-<id>.scope` (cgroup v2 +
systemd), `/docker/<id>` (cgroup v1 / cgroupfs), `cri-containerd-<id>.scope` and `/kubepods/…/<id>`
(containerd), `libpod-<id>.scope` (Podman). Fallback: the `hostname` / `hosts` / `resolv.conf` bind
mounts Docker makes from `/var/lib/docker/containers/<id>/`, in `/proc/<pid>/mountinfo`. Only
Docker containers can be asked; a containerd or Podman one is identified and its error says so.

### Node processes in a container

```json
{"pid": 1766, "uid": 0, "user": null, "version": "v20.11.1", "version_source": "container-exec",
 "path": "/usr/local/bin/node", "manager": "container", "deleted": false, "started": "…",
 "command": "node app/main.js", "error": null,
 "container": {"id": "1ec73de80a36", "name": "www-frontend-1", "image": "node:20-alpine", "runtime": "docker"}}
```

- `path` is the container-side path. The version is `docker exec <id> <path> --version`, cached
  per container and path.
- `user` is `null`: a uid inside a container is named by the image's passwd, not the host's.
  `by_user` counts the process under `container:<name>` (or `container:<short id>`), so counts
  still come from `by_user`.
- Host processes carry `container: null`.
- Errors are worded in container terms: `Container binary not inspected: docker socket not
  accessible …`, `Container binary has been replaced or removed since the process started`,
  `Container binary not identified …`.

### The `containers` block

```json
{"runtime": "docker", "error": null, "count": 6, "truncated": false, "items": [
  {"id": "9b977aa0aa3d", "name": "www-backend-1", "image": "storysloth-backend:latest",
   "image_id": "sha256:…", "created": "2026-10-30T22:10:01Z", "started_at": "2026-10-31T03:30:45Z",
   "status": "running", "restart_count": 0, "compose": {"project": "www", "service": "backend"},
   "os": {"id": "debian", "version": "12", "pretty_name": "Debian GNU/Linux 12 (bookworm)"},
   "packages": {"php": {"version": "8.4.27", "raw": "PHP 8.4.27 (cli) …"}, "php_fpm": {…},
                "node": null, "npm": null, "mysql": null, "mariadb": null, "postgres": null,
                "redis": null, "caddy": null, "nginx": null, "apache": null},
   "errors": []}]}
```

- **`null`** when Docker is not installed, or on macOS.
- **`error`** (with `items: []`, `count: null`) when Docker is there but cannot be asked: the
  socket is not accessible (not root / not in the docker group), the daemon is down. Unknown, never
  "no containers".
- **Running containers only.** `count` is every one `docker ps` listed; at most 100 are inspected.
- **`packages` has fixed keys.** `null` under a key means that binary was not found in the
  container; a found binary whose banner did not parse has `version: null` and keeps `raw`.
  `packages` itself is `null` when the container was not probed, and `errors` says why.
  MySQL and MariaDB are told apart by the banner (`mysqld` is a symlink in MariaDB images).
- **One `docker exec` per container** runs a POSIX `sh` script (busybox-safe) that asks each
  runtime for `--version` / `-v` and `cat`s `/etc/os-release`, of which only `ID`, `VERSION_ID` and
  `PRETTY_NAME` are kept. Versioned Debian `php-fpm8.x` and off-PATH Debian postgres are looked for
  where the packages put them. A container with no shell (distroless) gets each binary asked
  directly and its os-release read with `docker cp`, and `errors` notes it.
- **Never** the environment, a config file, or application data. `docker inspect` is asked for
  named fields through a template, not `{{json .}}`, which would carry the environment. Banners
  are capped at 200 characters and reduced to printable ASCII.
- **Bounded:** 3s per exec (host-side `timeout`, and in-container `timeout` where the image has
  it), 20s for the whole probe pass, 10s for the node-process pass. Containers are probed in name
  order; those the budget did not reach are still listed with `packages: null`, `errors:
  ["Not probed: the container pass ran out of time"]`, and the block's `truncated` is `true`.
- No writes inside containers, and nothing that would start a stopped one.

---

## Sudo access reporting

`Entity\Heartbeat\Sudoers` answers "who has sudo, and what may they run" by reading the privilege
specifications out of `/etc/sudoers` and every file in `/etc/sudoers.d`, and resolving the current
membership of any group one of those lines grants access to.

### Why group membership is read separately

A line like `%sudo ALL=(ALL:ALL) ALL` grants access to whoever is in the `sudo` group, not to a
fixed list — the sudoers file says nothing about who that is right now. `getent group <name>` is
run once per group named by a `%group` entry and its members are reported under a sibling `groups`
map, keyed by group name. A group that no longer exists on the host (removed since the sudoers
file was last edited) reports as `null` there, not `[]` — unknown, never "nobody is in it".

### What `parseSudoers()` recognises, and what it deliberately skips

One regex pulls `subject host=(run_as) [tags:] commands` out of a privilege specification line.
Everything else sudo's grammar allows is skipped rather than guessed at — sudo itself is the only
thing that has to parse this file correctly, and this is a best-effort inventory of it:

- Blank lines and `#`-comments.
- `Defaults` lines, whether global or scoped to one user (`Defaults:deploy !requiretty`).
- Alias definitions (`User_Alias`, `Cmnd_Alias`, `Runas_Alias`, `Host_Alias`) — **never expanded**.
  A line that uses an alias reports the alias name verbatim as the `subject` or in `commands`, not
  what it resolves to.
- Include directives, both the legacy `#include`/`#includedir` form and the modern
  `@include`/`@includedir` form. `/etc/sudoers.d` is already read directly regardless of what a
  file's own directive names, so a directive naming it is simply skipped rather than followed.

### Files read, and how a permission failure is reported

`/etc/sudoers` is mode `0440 root:root`; `/etc/sudoers.d` is `0750 root:root`. Both need root to
read. Each file is reported as `{entries, error}` — a fixed pair, always both present — so a read
failure is never confused with a file that was readable and simply held nothing. A single
unreadable drop-in degrades only that file's own entry; it does not cost the ones that could be
read, the same per-file behaviour `/etc/cron.d` gets in the cron block. `/etc/sudoers.d` itself
failing to be listed is reported at the block's own top level, distinct from one file inside it
failing.

Drop-ins with a dot in the name or a trailing `~` are skipped, the same convention `run-parts` and
`/etc/cron.d` use — sudo itself ignores them too.

### Commands are redacted the same way cron's are

A sudoers entry is just as capable of having a credential written into it as a cron command is
(`-pSECRET`, `--password=`, `Bearer …`), so each command in the list is passed through
`Helper\Redact::secrets()` before it leaves the server.

---

## Account and SSH key reporting

`Entity\Heartbeat\Accounts` answers "who is on this server, and which keys let them in". Its main
use is finding every server that still trusts a given person's key — an ex-employee's, say — by
fingerprint or by comment.

### Accounts

Every entry `getent passwd` returns (falling back to `/etc/passwd`), sorted by uid and capped at
2,000 with a `truncated` flag, keyed by name. Each carries its uid/gid, its groups (primary first,
then supplementary, from one `getent group` read), gecos, home and shell, and `login_shell` — false
for `nologin`, `false` and friends. **All accounts are reported, not just humans**: a key in
`www-data`'s home is exactly the kind of thing worth seeing, and a `nologin` shell does not stop a
key with a forced `command=`.

`password` is reduced from `/etc/shadow` to `set`, `locked` (any `!`/`*` form) or `none` (an empty
hash). The hash itself is never sent. A locked password does **not** stop key login — that is why
the keys are reported next to it. `expires` is shadow's account expiry as `Y-m-d`, or `null` for
never. Without root `/etc/shadow` is unreadable: both are then `null` and the block's
`shadow_error` says why.

### Keys

The files read for each account are sshd's own `AuthorizedKeysFile` patterns, taken from
`sshd -T` (`%h`, `%u`, `%U` and `%%` expanded, relative paths taken from the home), falling back to
sshd's default `.ssh/authorized_keys .ssh/authorized_keys2` when that cannot be run. Each file is
reported as `{keys, invalid, truncated, error}`, keyed by path. A file that does not exist is left
out; a file that exists but could not be read, or that this declined to read, carries an `error`
in place of its keys, so it is never confused with an empty one. Without root a 0700 `~/.ssh`
makes its files look absent, so that case is an `error` too.

Each key is `{type, bits, fingerprint, comment, options}`. The fingerprint is computed in PHP from
the decoded blob — the same `SHA256:…` string `ssh-keygen -lf` prints — so nothing is executed per
key. A line is only accepted as a key when its base64 decodes and the type encoded inside the blob
matches the declared one; anything else is counted in `invalid` rather than dropped silently.
Comments and options (`command="…"`, `environment="…"`) pass through `Helper\Redact::secrets()`
first, the same as cron commands.

### What is refused, and why

The heartbeat runs as root. A file reached through a home directory must resolve to somewhere
still inside that home, and any key file must be owned by the account or by root — the same guard
`Node` applies to files it reads from a home, and what sshd's `StrictModes` demands before it uses
the file at all. A symlink from `~/.ssh/authorized_keys` to `/etc/shadow` is reported as an error,
never read.

### sshd

`present` is whether `/usr/sbin/sshd` exists. Alongside it, from `sshd -T`: `permit_root_login`,
`pubkey_authentication`, `password_authentication`, `kbd_interactive_authentication`,
`authorized_keys_files`, `authorized_keys_command` (+ `_user`), and the Allow/Deny user and group
lists. `sshd -T` is evaluated without a connection, so `Match` blocks are not reflected. **An
`authorized_keys_command` means the files are not the whole picture** — GCP OS Login and EC2
Instance Connect hand sshd keys from a command, and those keys are not on disk to be reported.

---

## MySQL version reporting

`Entity\Heartbeat\Mysql` reports the **server** binary on the box — never a
client login, never `~/.mysql-root-password`. It looks at fixed paths
(`/usr/sbin/mariadbd`, `/usr/sbin/mysqld`, `/usr/bin/mariadbd`,
`/usr/bin/mysqld`) and runs `--version` only when the real path is owned by
root and not group- or other-writable, with `env -i` and a 5s `timeout`.

The engine is taken from the banner (`MariaDB` in the text vs not). Ubuntu's
MariaDB still ships a `mysqld` symlink, so the filename is not authoritative.

If the binary exists but cannot be asked, it falls back to `dpkg-query` on
`mysql-server` / `mysql-server-core-*` / `mariadb-server` and marks
`version_source` as `package`. If nothing is installed:

```json
{"present": false, "engine": null, "version": null, "version_full": null, "version_source": null, "error": null}
```

`version: null` means unknown, never `0` or an empty string. The whole block is
`null` on macOS. A missing database does **not** throw — that would abort the
heartbeat.

---

## Docker reporting

`Entity\Heartbeat\Docker` reports whether Docker Engine is installed, which
version it runs, and how many containers it has. It exists so observers know a
host's reported MySQL / PHP / Node versions may not be what its containers run.

- **Installed** means a `dockerd` binary (`/usr/bin/dockerd`, `/usr/sbin/dockerd`,
  `/usr/local/bin/dockerd`) or an installed `docker-ce` / `docker.io` /
  `moby-engine` package. A client alone is not a Docker host.
- **Version**, best first: the running daemon via
  `docker info --format '{{.ServerVersion}}|{{.ContainersRunning}}|{{.Containers}}'`
  (`version_source: daemon`), then `dockerd --version` (`binary`), then dpkg
  (`package`). Binaries are executed only when root owns every path component,
  under `env -i` (an inherited `DOCKER_HOST` cannot redirect the client) and a 5s
  `timeout`.
- **Containers** come from the same `docker info` call. With no daemon the CLI
  still prints the template as `|0|0` (exiting 1), so counts with no server
  version are discarded as unknown — `null`, never `0`.

```json
{"present": true, "version": "27.3.1", "version_full": "27.3.1", "version_source": "daemon", "package": "docker-ce", "daemon": "active", "containers": {"running": 4, "total": 6}, "error": null}
```

Not installed is `present: false` with every other key `null`; the whole block is
`null` on macOS. Nothing here throws.

---

## Elasticsearch reporting

`Entity\Heartbeat\Elasticsearch` reports whether an Elasticsearch server is
installed, its version, and whether its unit is running.

- **Installed** means the `elasticsearch` dpkg package (Elastic's apt
  repository) or `/usr/share/elasticsearch/bin/elasticsearch`. An Elasticsearch
  running inside a container is not seen here; the `docker` block flags that
  host instead.
- **Version** comes from dpkg (`version_source: package`), else from the
  server jar's filename, `lib/elasticsearch-<version>.jar` (`path`; sibling jars
  such as `elasticsearch-core-*` are skipped).
- **Nothing is executed and the node is never asked.** Elasticsearch 8 requires
  authentication on port 9200 by default, and the launcher starts a JVM, so
  neither is worth it for a version string.

```json
{"present": true, "version": "8.15.0", "version_full": "8.15.0", "version_source": "package", "package": "elasticsearch", "daemon": "active", "error": null}
```

Not installed is `present: false` with every other key `null`; the whole block
is `null` on macOS. Nothing here throws.

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
