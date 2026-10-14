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
</p>

The UI is in German; the codebase follows English Laravel conventions.

## Features

- **Monthly overview** — incomes, cost shares, remaining budget and the balancing transfers between participants, with per-person cost percentages
- **Participant detail pages** — every cost item affecting a person in a given month
- **Split rules** — fixed percentages, income-weighted, equal shares, or personal (one person carries 100 %, no matter who paid)
- **Schedules** — one-off, monthly, quarterly, every 4 months, half-yearly or yearly costs with start/end months
- **Month closing** — freezes an immutable snapshot and renders the PDF statement
- **Year planning** — twelve months at a glance, closed months use their frozen snapshot
- **Backup & restore** — export/import the whole dataset (participants, incomes, expenses, closings) as a ZIP, e.g. for moving to another instance
- **Admin-only logins** — no public registration; user management lives in the profile dropdown

Amounts are stored as integer cents; each expense is allocated with the largest-remainder method so no cent is lost.

## Quick start (Dev Container)

Requirements: Docker + VS Code with the Dev Containers extension.

1. Open this folder and run **Dev Containers: Reopen in Container**. Composer packages, the app key and database migrations are set up automatically.
2. Inside the container terminal:

```sh
php artisan splitnest:user daniel@example.org --name=Daniel   # create an admin login (hidden password prompt, min. 12 chars)
php artisan db:seed --class=HouseholdDemoSeeder               # optional: editable demo data starting 2026-01
```

3. Open <http://localhost:8080/admin> and log in.

Change the port with `APP_PORT=8088 docker compose up -d`. Data lives in a named PostgreSQL volume and survives restarts (`down --volumes` would delete it).

## Production (Docker)

```sh
cp .env.production.example .env.production
# generate an app key once, e.g. inside the dev container:
docker compose exec app php artisan key:generate --show
```

Put the generated key, your HTTPS `APP_URL` and a long random `DB_PASSWORD` into `.env.production`, then:

```sh
docker compose --env-file .env.production -f compose.production.yaml build app
docker compose --env-file .env.production -f compose.production.yaml up -d db
docker compose --env-file .env.production -f compose.production.yaml run --rm app php artisan migrate --force
docker compose --env-file .env.production -f compose.production.yaml up -d app
docker compose --env-file .env.production -f compose.production.yaml exec app php artisan splitnest:user
```

Notes: the production image runs as `www-data`, ships no secrets and exposes a `/up` healthcheck. PostgreSQL publishes no port. The app binds to `127.0.0.1` by default — run an HTTPS reverse proxy in front of it and set `TRUSTED_PROXIES` to its address(es). `SESSION_SECURE_COOKIE=true` requires HTTPS; set it to `false` only for a purely local HTTP test.

Pre-built release images are published to the GitHub Container Registry on every release.

## Backup & restore

Use the **Sicherung** page in the panel: *Backup herunterladen* exports participants, incomes, expenses and monthly closings as a ZIP; *Backup einspielen* replaces the whole dataset with a backup file (works across instances). User accounts are not exported on purpose — recreate logins with `php artisan splitnest:user`.

For full disaster recovery additionally back up PostgreSQL, the storage volume and the app key:

```sh
docker compose --env-file .env.production -f compose.production.yaml exec -T db pg_dump -U household household > household-backup.sql
```

## Development

The dev container ships PHP 8.4, Composer, Node 22, pre-commit and gitleaks. Everything is set up by `scripts/dev-setup.sh`.

```sh
composer run dev        # serve with artisan serve
php artisan test        # run the test suite (always against in-memory SQLite)
vendor/bin/pint         # fix code style
```

### Code standards

- [Laravel Pint](https://laravel.com/docs/pint) (Laravel preset) for code style
- Every class and method in `app/`, `database/factories` and `database/seeders` must carry a PHPDoc block — checked by `php scripts/check-phpdocs.php`
- Feature tests with PHPUnit for behaviour changes
- **Conventional Commits** (`feat:`, `fix:`, …) so releases can be generated automatically

Git hooks via [pre-commit](https://pre-commit.com) run gitleaks, Pint, the PHPDoc check and the test suite on every commit (`pre-commit install` happens in `dev-setup.sh`).

### CI/CD

- **Tests** workflow: Pint, PHPDoc coverage, PHPUnit and gitleaks on pushes/PRs
- **Build** workflow: frontend assets plus a production Docker test build
- **Release** workflow: [semantic-release](https://semantic-release.gitbook.io/) creates tags, GitHub releases and the changelog (attached as a release asset), then publishes the Docker image to GHCR with the version baked in as `APP_VERSION` (shown in the panel footer)

Versioning starts at **v0.0.1**: create the initial tag once before the first release run:

```sh
git tag v0.0.0 && git push origin v0.0.0
```

## License

[MIT](composer.json)
