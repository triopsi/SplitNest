<p align="center">
  <img src="public/brand/splitnest-logo-dark.svg" alt="SplitNest logo" width="360">
</p>

<h1 align="center">SplitNest</h1>

<p align="center">
  <b>Shared expenses. Fair contributions.</b><br>
  Household budgeting, cost splitting and monthly PDF statements for shared homes.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/License-MIT-3da639" alt="MIT License">
  <a href="https://github.com/triopsi/SplitNest/actions/workflows/tests.yml"><img src="https://github.com/triopsi/SplitNest/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="https://github.com/triopsi/SplitNest/actions/workflows/build.yml"><img src="https://github.com/triopsi/SplitNest/actions/workflows/build.yml/badge.svg" alt="Build"></a>
  <a href="https://github.com/triopsi/SplitNest/releases"><img src="https://img.shields.io/github/v/release/triopsi/SplitNest" alt="GitHub release (latest by date)"></a>
</p>

SplitNest helps shared households split expenses fairly, track their budget and create monthly statements. Available in English and German, with passwordless sign-in using passkeys.

[Quick start](#quick-start) · [HTTPS & reverse proxy](#https--reverse-proxy) · [Updates](#updates) · [Backups](#backups) · [Development](#development)

## Quick start

You need [Docker with Docker Compose](https://docs.docker.com/get-started/get-docker/).

**1. Create a folder for SplitNest and save this as `compose.yaml`:**

```yaml
services:
  app:
    image: triopsi/splitnest:latest
    restart: unless-stopped
    ports:
      - "127.0.0.1:8080:8080"
    environment:
      APP_URL: http://localhost:8080
      DB_CONNECTION: sqlite
      DB_DATABASE: /var/www/html/storage/database.sqlite
      AUTO_MIGRATE: "true"
      SESSION_SECURE_COOKIE: "false"
      LOG_CHANNEL: stderr
    volumes:
      - storage:/var/www/html/storage

volumes:
  storage:
```

**2. Start SplitNest in that folder:**

```sh
docker compose up -d
```

**3. Open [localhost:8080/admin](http://localhost:8080/admin) and create your administrator account.**

That's it. The container creates its encryption key and SQLite database automatically and runs database migrations on startup. Both are kept in the `storage` volume across restarts and updates. No repository clone, local PHP, Node.js or `.env` file is needed.

The example is for a local HTTP setup. For a server or passkeys, continue with HTTPS below. The same Compose file is also available as [compose.production.yaml](compose.production.yaml).

## What you can do

- **Split expenses fairly** — equal shares, fixed percentages, income-based shares or personal expenses.
- **See who owes whom** — monthly income, expenses, remaining budgets and balancing transfers.
- **Plan recurring costs** — one-off expenses and recurring schedules, with a year overview and charts.
- **Close a month** — save a snapshot, generate a PDF statement and send it by e-mail. Reopen a month when something needs correcting.
- **Manage your household** — invite users by e-mail and manage accounts as an administrator.
- **Sign in with passkeys** — register a passkey on your profile for passwordless login over HTTPS or localhost.
- **Keep a backup** — export and restore your household data from the app.
- **Choose your language** — switch between English and German in the topbar.

## HTTPS & reverse proxy

Point your reverse proxy at SplitNest's HTTP port `8080`. Add your public URL and proxy address to the app's `environment` section:

```yaml
      APP_URL: https://splitnest.example.org
      TRUSTED_PROXIES: "10.20.0.10"
      SESSION_SECURE_COOKIE: "true"
```

Replace the URL and IP with your own values. Configure the proxy to forward the original host and `X-Forwarded-Proto: https`, then apply the changes:

```sh
docker compose up -d
```

If your proxy runs on another machine, change the port mapping to `"8080:8080"` so it can reach SplitNest over the network. If it runs on the same host, keep `"127.0.0.1:8080:8080"`. With a proxy in Docker, you can instead connect both containers to a shared Docker network and target `app:8080`.

Open your HTTPS URL and create your administrator account if you haven't already. You can add passkeys on your profile page.

## Updates

In your installation folder, run:

```sh
docker compose pull
docker compose up -d --remove-orphans
```

Your data and generated key stay in the volume. Database migrations run automatically. Back up before updating.

To use a specific release, replace `:latest` in the image name with the version number from [Releases](https://github.com/triopsi/SplitNest/releases). Use a release that includes automatic container initialization for the quick-start example.

**Already running SplitNest with MariaDB and an `APP_KEY`?** Keep your existing database, volume mounts and key. You can update the image without switching to the SQLite example. An explicitly configured `APP_KEY` always takes priority over automatic key generation. Existing installations with a separate `migrate` service can keep using it; automatic migrations are enabled only when `AUTO_MIGRATE: "true"` is set.

## Optional configuration

Add settings under `services.app.environment`, then run `docker compose up -d`.

### E-mail

To send invitations, password resets and monthly statements, add your SMTP settings:

```yaml
      MAIL_MAILER: smtp
      MAIL_SCHEME: smtp
      MAIL_HOST: mail.example.org
      MAIL_PORT: "587"
      MAIL_USERNAME: no-reply@example.org
      MAIL_PASSWORD: "your-smtp-password"
      MAIL_FROM_ADDRESS: no-reply@example.org
      MAIL_FROM_NAME: SplitNest
```

Port `587` typically uses `smtp` with automatic STARTTLS; for implicit TLS on port `465`, use `smtps`. Without SMTP settings, messages are written to the container log instead of sent.

### Language

```yaml
      APP_LOCALE: de
```

English is the default. Users can also switch languages in the topbar.

<details>
<summary><strong>Use MariaDB instead of SQLite</strong></summary>

For a new MariaDB installation, use this Compose file instead of the quick-start example. Set `DB_PASSWORD` and `MARIADB_ROOT_PASSWORD` in an `.env` file next to it; choose two different strong passwords. No manual application-key generation is needed.

```yaml
services:
  app:
    image: triopsi/splitnest:latest
    restart: unless-stopped
    ports:
      - "127.0.0.1:8080:8080"
    environment:
      APP_URL: http://localhost:8080
      DB_CONNECTION: mariadb
      DB_HOST: db
      DB_PORT: "3306"
      DB_DATABASE: household
      DB_USERNAME: household
      DB_PASSWORD: ${DB_PASSWORD:?Set DB_PASSWORD in .env}
      AUTO_MIGRATE: "true"
      SESSION_SECURE_COOKIE: "false"
      LOG_CHANNEL: stderr
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
      MARIADB_PASSWORD: ${DB_PASSWORD:?Set DB_PASSWORD in .env}
      MARIADB_ROOT_PASSWORD: ${MARIADB_ROOT_PASSWORD:?Set MARIADB_ROOT_PASSWORD in .env}
    volumes:
      - database:/var/lib/mysql
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 10
volumes:
  storage:
  database:
```

Run `docker compose up -d`, then open [localhost:8080/admin](http://localhost:8080/admin). Use the HTTPS settings above when hosting behind a reverse proxy. Switching an existing SQLite installation to MariaDB requires a separate data migration.

</details>

<details>
<summary><strong>More environment settings</strong></summary>

| Setting | Default / purpose |
|---|---|
| `APP_NAME` | `SplitNest`; branding and sender name |
| `APP_KEY` | Generated in `storage/app.key` when omitted; keep your existing value on upgrades |
| `APP_URL` | Set to the URL used in your browser; also used in e-mails and passkey configuration |
| `APP_LOCALE` | `en`; use `de` for German |
| `TRUSTED_PROXIES` | Your reverse proxy IP address(es), comma-separated |
| `AUTO_MIGRATE` | `false`; the quick start enables it to migrate before starting the server |
| `SESSION_SECURE_COOKIE` | `false` for the local HTTP example, `true` with HTTPS |
| `SESSION_LIFETIME` | `120` minutes |
| `AUTH_PASSWORD_RESET_EXPIRE` | `1440` minutes |
| `HOUSEHOLD_INVITATION_TTL` | `24` hours |
| `CACHE_STORE` / `SESSION_DRIVER` | `database` |
| `QUEUE_CONNECTION` | `database`; a worker is needed for jobs explicitly dispatched to the queue |
| `LOG_CHANNEL` | The Compose examples use `stderr` for `docker compose logs` |

Docker images already contain PHP dependencies and built frontend assets. Keep `APP_DEBUG` disabled on your server. For Compose's `.env` interpolation, escape literal dollar signs as `$$` in values written directly into the Compose file.

</details>

## Backups

For a household-data export, open **Backup** in the app. The ZIP includes participants, incomes, expenses and monthly closings. User accounts are excluded.

For a complete backup of the SQLite quick-start installation, stop the app briefly and archive its storage volume:

```sh
docker compose stop app
docker compose run --rm --no-deps --entrypoint tar app -czf - -C /var/www/html storage > splitnest-backup.tar.gz
docker compose start app
```

Keep this archive together with your Compose file. It includes the SQLite database, uploads and generated encryption key. If you set `APP_KEY` yourself, back up that value too. For MariaDB, also back up the database separately, for example:

```sh
docker compose exec -T db sh -c 'exec mariadb-dump --single-transaction -u "$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' > splitnest-database.sql
```

`docker compose down` keeps named volumes. **`docker compose down --volumes` deletes them**, including your database and generated key.

## Troubleshooting

- **Container won't start?** Run `docker compose logs --tail=100 app` and check for a migration or storage-permission error.
- **Can't reach the app from another machine?** The example listens on localhost only. Use a reverse proxy or adjust the port mapping as described above.
- **Passkey button does nothing?** Use HTTPS or localhost, update to the current image, and reload the page. Check the browser console for failed asset requests.
- **Invitations don't arrive?** Configure SMTP; the default mail driver only writes messages to the log.

## Development

The root [compose.yaml](compose.yaml) is the development stack. For running a released image, use the quick start above.

With Docker, VS Code and the Dev Containers extension installed:

1. Clone the repository and open it in VS Code.
2. Run **Dev Containers: Reopen in Container**. `scripts/dev-setup.sh` installs dependencies, generates the development key and runs migrations.
3. Open [localhost:8080/admin](http://localhost:8080/admin) and register the first administrator.

Development uses PostgreSQL by default. [compose.mariadb.yaml](compose.mariadb.yaml) provides a MariaDB development variant.

```sh
php artisan test                   # in-memory SQLite, independent of development data
php artisan test --group=critical  # money, authentication and other critical behaviour
vendor/bin/pint                     # fix PHP code style
npm run build                      # rebuild frontend assets when Node.js is available
```

### Code standards

- [Laravel Pint](https://laravel.com/docs/pint) (Laravel preset) for code style
- Every class and method in `app/` and `database/factories` must carry a PHPDoc block — checked by `php scripts/check-phpdocs.php`
- Feature tests with PHPUnit for behaviour changes
- **Conventional Commits** (`feat:`, `fix:`, …) so releases can be generated automatically

Git hooks via [pre-commit](https://pre-commit.com) run gitleaks, Pint, the PHPDoc check and the test suite on every commit (`pre-commit install` happens in `dev-setup.sh`).

### CI/CD

- **Tests** workflow: Pint, PHPDoc coverage, PHPUnit and gitleaks on pushes/PRs
- **Build** workflow: frontend assets plus a production Docker test build
- **Release** workflow: [semantic-release](https://semantic-release.gitbook.io/) creates tags, GitHub releases and the changelog (attached as a release asset), then publishes the Docker image to [Docker Hub](https://hub.docker.com/r/triopsi/splitnest) with the version baked in as `APP_VERSION` (shown in the panel footer). Images support `linux/amd64` and `linux/arm64` and are tagged with the release version and `latest`.

## Translations

The UI base language is **English**; German is included as a complete translation. Users switch the language in the topbar dropdown next to the user menu (remembered per session); the default for fresh sessions is set with `APP_LOCALE` (e.g. `APP_LOCALE=de` in your `.env`). Panel chrome (buttons, pagination, login form) is translated by Filament itself.

Missing your language? **Contributions are very welcome!** Translations live in `lang/<locale>/` as plain PHP array files, grouped by area (`settlement.php`, `yearplan.php`, `expenses.php`, `incomes.php`, `participants.php`, `schedule.php`, `users.php`, `backup.php`, `settings.php`, `mail.php`, `pdf.php`, `nav.php`). To add your language:

1. Copy the English files: `cp -r lang/en lang/<your-locale>` (e.g. `lang/fr`)
2. Translate the values — keep the keys and `:placeholder`/`{placeholder}` names untouched
3. Open a pull request

New locales appear in the switcher automatically (add the native name to `App\Support\Locales`). The test suite compares every locale against the English keys, so a PR with gaps fails fast and translations stay maintained.

## License

[MIT](LICENSE). Contributions, bug reports and translations are welcome.
