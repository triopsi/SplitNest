<p align="center">
  <img src="public/brand/splitnest-logo-dark.svg" alt="SplitNest logo" width="360">
</p>

<h1 align="center">SplitNest</h1>

<p align="center">
  <b>Shared expenses. Fair contributions.</b><br>
  Household budgeting, cost splitting and monthly PDF statements for shared homes.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4" alt="PHP 8.4">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20" alt="Laravel 13">
  <img src="https://img.shields.io/badge/Filament-5-FDAE4B" alt="Filament 5">
  <img src="https://img.shields.io/badge/License-MIT-3da639" alt="MIT License">
  [![Tests](https://github.com/triopsi/SplitNest/actions/workflows/tests.yml/badge.svg)](https://github.com/triopsi/SplitNest/actions/workflows/tests.yml)
  [![Build](https://github.com/triopsi/SplitNest/actions/workflows/build.yml/badge.svg)](https://github.com/triopsi/SplitNest/actions/workflows/build.yml)
  [![Release](https://github.com/triopsi/SplitNest/actions/workflows/release.yml/badge.svg)](https://github.com/triopsi/SplitNest/actions/workflows/release.yml)
  [![Downloads](https://img.shields.io/github/downloads/triopsi/SplitNest/total)]()
  [![GitHub release (latest by date)](https://img.shields.io/github/v/release/triopsi/SplitNest)](https://github.com/triopsi/SplitNest/releases)
</p>

The UI is in German; the codebase follows English Laravel conventions.

## Features

- **Monthly overview** — incomes, cost shares, remaining budget and the balancing transfers between participants, with per-person cost percentages
- **Participant detail pages** — every cost item affecting a person in a given month
- **Split rules** — fixed percentages, income-weighted, equal shares, or personal (one person carries 100 %, no matter who paid)
- **Schedules** — one-off, monthly, quarterly, every 4 months, half-yearly or yearly costs with start/end months
- **Year planning** — twelve months at a glance, closed months use their frozen snapshot
- **Charts** — doughnut of the month's cost shares per person and a year overview comparing incomes vs. costs per month
- **Month closing** — freezes an immutable snapshot and renders the PDF statement; a closing can be reopened when something was forgotten and closed again afterwards
- **Closing e-mails** — participants automatically receive the PDF statement by mail when a month is closed; the body text is configurable with placeholders
- **Backup & restore** — export/import the whole dataset (participants, incomes, expenses, closings) as a ZIP, e.g. for moving to another instance
- **E-mail invitations** — admins invite household members by mail; the one-time link (24 h by default, configurable via `.env`) leads to a secure self-registration
- **Password reset** — "forgot password" flow with an e-mail token, valid 24 h by default and configurable via `.env`
- **Admin-only logins** — the very first administrator registers through a first-run form (only while no account exists); further users are created by admins or invited by e-mail
- **Settings page** — closing mail text and optional Gravatar avatars
- **Database choice** — MariaDB in production, PostgreSQL or MariaDB for development, SQLite for the test suite

Amounts are stored as integer cents; each expense is allocated with the largest-remainder method so no cent is lost.

## Quick start (Dev Container)

Requirements: Docker + VS Code with the Dev Containers extension.

1. Open this folder and run **Dev Containers: Reopen in Container**. Composer packages, the app key and database migrations are set up automatically.
2. Open <http://localhost:8080/admin>: as long as no account exists, a registration form for the first administrator appears automatically.

Remember: the first user is always created through the web interface, never via the console. Change the port with `APP_PORT=8088 docker compose up -d`. Data lives in a named PostgreSQL volume and survives restarts (`down --volumes` would delete it).

### Alternative: Docker Compose with MariaDB

If you prefer plain Docker over the Dev Container — or MariaDB over PostgreSQL — use the MariaDB variant, which mirrors the dev setup:

```sh
docker compose -f compose.mariadb.yaml up -d --build
docker compose -f compose.mariadb.yaml exec app composer install
docker compose -f compose.mariadb.yaml exec app php artisan key:generate
docker compose -f compose.mariadb.yaml exec app php artisan migrate
```

Then open <http://localhost:8080/admin> and register the first administrator in the browser. The app connects with `DB_CONNECTION=mariadb`; data lives in its own named volume (`database-mariadb`), so it never clashes with the PostgreSQL volume of `compose.yaml`.

## Quick Start (Production)

Homelab-friendly: no build, no repo clone — release images come prebuilt from the GitHub Container Registry.

1. [Install Docker](https://docs.docker.com/get-docker/)
2. Create a `compose.yaml` file like this:

   ```yaml
   services:
     app:
       image: ghcr.io/triopsi/splitnest:${SPLITNEST_VERSION:-latest}
       restart: unless-stopped
       ports:
         - "${APP_BIND_ADDRESS:-127.0.0.1}:${APP_PORT:-8080}:8080"
       environment: &app-environment
         APP_NAME: SplitNest
         APP_ENV: production
         APP_DEBUG: "false"
         APP_KEY: ${APP_KEY}
         APP_URL: ${APP_URL}
         APP_LOCALE: de
         DB_CONNECTION: mariadb
         DB_HOST: db
         DB_PORT: 3306
         DB_DATABASE: household
         DB_USERNAME: household
         DB_PASSWORD: ${DB_PASSWORD}
         SESSION_DRIVER: database
         SESSION_SECURE_COOKIE: ${SESSION_SECURE_COOKIE:-true}
         CACHE_STORE: database
         QUEUE_CONNECTION: database
         MAIL_MAILER: log
         LOG_CHANNEL: stderr
         TRUSTED_PROXIES: ${TRUSTED_PROXIES:-}
       volumes:
         - storage:/var/www/html/storage
       depends_on:
         db:
           condition: service_healthy
         migrate:
           condition: service_completed_successfully
     migrate:
       image: ghcr.io/triopsi/splitnest:${SPLITNEST_VERSION:-latest}
       restart: "no"
       command: [php, artisan, migrate, --force]
       environment: *app-environment
       volumes:
         - storage:/var/www/html/storage
       depends_on:
         db:
           condition: service_healthy
     db:
       image: mariadb:11
       restart: unless-stopped
       environment:
         MARIADB_DATABASE: household
         MARIADB_USER: household
         MARIADB_PASSWORD: ${DB_PASSWORD}
         MARIADB_ROOT_PASSWORD: ${MARIADB_ROOT_PASSWORD}
       volumes:
         - database:/var/lib/mysql
       healthcheck:
         test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
         interval: 5s
         timeout: 5s
         retries: 10
   volumes:
     database:
     storage:
   ```

3. Create an `.env` file next to it and fill in the values (template: [.env.production.example](.env.production.example)):

   ```dotenv
   APP_KEY=
   APP_URL=https://splitnest.example.org
   DB_PASSWORD=
   MARIADB_ROOT_PASSWORD=
   ```

   Generate the app key once:

   ```sh
   docker run --rm ghcr.io/triopsi/splitnest:latest php artisan key:generate --show
   ```

4. Bring up your stack by running:

   ```sh
   docker compose up -d
   ```

5. Open your URL and register the first administrator in the browser. The first user is always created through the web interface, never via the console.

Migrations run automatically on every start (one-shot `migrate` service). Pin a release with `SPLITNEST_VERSION=1.4.0` in the `.env` (default: `latest`); update with `docker compose pull && docker compose up -d`.

Notes: the image runs as `www-data`, ships no secrets and exposes a `/up` healthcheck. MariaDB publishes no port. The app binds to `127.0.0.1` by default — run an HTTPS reverse proxy in front of it and set `TRUSTED_PROXIES` to its address(es). `SESSION_SECURE_COOKIE=true` requires HTTPS; set it to `false` only for a purely local HTTP test.

## Environment variables

Everything the app reads from the environment, with its defaults. In production, set them in the `.env` next to the compose file; the compose file passes them into the container.

### Application

| Variable | Default | Purpose |
|---|---|---|
| `APP_NAME` | `SplitNest` | Application name (branding, mail sender name) |
| `APP_ENV` | `production` | Laravel environment |
| `APP_DEBUG` | `false` | Debug mode — never `true` in production |
| `APP_KEY` | *(required)* | Encryption key, generate per the production steps |
| `APP_URL` | `http://localhost` | Public base URL, used for links in e-mails |
| `APP_LOCALE` | `de` | UI locale |
| `APP_FALLBACK_LOCALE` | `en` | Fallback locale |
| `APP_VERSION` | `dev` | Version shown in the footer; baked into release images |
| `TRUSTED_PROXIES` | *(empty)* | Reverse proxy IP address(es), comma-separated |

### Database

| Variable | Default | Purpose |
|---|---|---|
| `DB_CONNECTION` | `sqlite` | Driver; the production compose file sets `mariadb` |
| `DB_HOST` | `127.0.0.1` | Database host (`db` via compose) |
| `DB_PORT` | `3306` / `5432` | Driver default; `3306` via compose |
| `DB_DATABASE` | `household` via compose | Database name |
| `DB_USERNAME` | `household` via compose | Database user |
| `DB_PASSWORD` | *(empty)* | Database password — required in production |

### Security & tokens

| Variable | Default | Purpose |
|---|---|---|
| `BCRYPT_ROUNDS` | `12` | Password hashing cost |
| `AUTH_PASSWORD_RESET_EXPIRE` | `1440` | Password reset token validity in minutes (24 h) |
| `HOUSEHOLD_INVITATION_TTL` | `24` | Invitation link validity in hours |
| `AUTH_PASSWORD_TIMEOUT` | `10800` | Seconds before password re-confirmation is asked |

### Session, cache & queue

| Variable | Default | Purpose |
|---|---|---|
| `SESSION_DRIVER` | `database` | Session storage |
| `SESSION_LIFETIME` | `120` | Session lifetime in minutes |
| `SESSION_ENCRYPT` | `false` | Encrypt session cookies |
| `SESSION_SECURE_COOKIE` | `true` in production | HTTPS-only cookies; `false` only for local HTTP tests |
| `CACHE_STORE` | `database` | Cache storage |
| `QUEUE_CONNECTION` | `database` | Queue driver |

### Mail

| Variable | Default | Purpose |
|---|---|---|
| `MAIL_MAILER` | `log` | Mail driver; `log` only writes to the log — configure `smtp` so invitations and password resets actually reach inboxes |
| `MAIL_HOST` | `127.0.0.1` | SMTP server |
| `MAIL_PORT` | `2525` | SMTP port |
| `MAIL_SCHEME` | `null` | SMTP encryption (`tls` / `ssl`) |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | `null` | SMTP credentials |
| `MAIL_FROM_ADDRESS` | `hello@example.com` | Sender address |
| `MAIL_FROM_NAME` | `${APP_NAME}` | Sender name |

### Compose file only

| Variable | Default | Purpose |
|---|---|---|
| `SPLITNEST_VERSION` | `latest` | Image tag to pull, e.g. `1.4.0` |
| `APP_PORT` | `8080` | Host port mapping |
| `APP_BIND_ADDRESS` | `127.0.0.1` | Host bind address |
| `MARIADB_ROOT_PASSWORD` | *(required)* | MariaDB root password |

## Backup & restore

Use the **Sicherung** page in the panel: *Backup herunterladen* exports participants, incomes, expenses and monthly closings as a ZIP; *Backup einspielen* replaces the whole dataset with a backup file (works across instances). User accounts are not exported on purpose — on the target instance the first administrator registers through the web form, further users are invited by e-mail.

For full disaster recovery additionally back up the database, the storage volume and the app key:

```sh
docker compose exec -T db mariadb-dump -u household -p"$DB_PASSWORD" household > household-backup.sql
```

## Development

The dev container ships PHP 8.4, Composer, Node 22, pre-commit and gitleaks. Everything is set up by `scripts/dev-setup.sh`.

```sh
composer run dev        # serve with artisan serve
php artisan test        # run the test suite (always against in-memory SQLite)
php artisan test --group=critical   # only the safety- and money-critical tests
vendor/bin/pint         # fix code style
```

Every test method documents what it does and the expected outcome in its PHPDoc. Tests guarding money conservation, closing immutability, backups and authentication are marked `#[Group('critical')]`.

### Code standards

- [Laravel Pint](https://laravel.com/docs/pint) (Laravel preset) for code style
- Every class and method in `app/` and `database/factories` must carry a PHPDoc block — checked by `php scripts/check-phpdocs.php`
- Feature tests with PHPUnit for behaviour changes
- **Conventional Commits** (`feat:`, `fix:`, …) so releases can be generated automatically

Git hooks via [pre-commit](https://pre-commit.com) run gitleaks, Pint, the PHPDoc check and the test suite on every commit (`pre-commit install` happens in `dev-setup.sh`).

### CI/CD

- **Tests** workflow: Pint, PHPDoc coverage, PHPUnit and gitleaks on pushes/PRs
- **Build** workflow: frontend assets plus a production Docker test build
- **Release** workflow: [semantic-release](https://semantic-release.gitbook.io/) creates tags, GitHub releases and the changelog (attached as a release asset), then publishes the Docker image to GHCR with the version baked in as `APP_VERSION` (shown in the panel footer)

## License

Licensed under the [MIT License](https://github.com/triopsi/SplitNest/blob/master/LICENSE).
