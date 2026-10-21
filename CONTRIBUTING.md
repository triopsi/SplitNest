# Contributing to SplitNest

Thanks for helping improve SplitNest! Contributions of all sizes are welcome, including bug reports, documentation improvements, translations, tests and code.

SplitNest uses PHP 8.4+, Laravel 13, Filament 5 and Livewire. Familiarity with these tools helps when changing the application, but documentation and bug reports are equally welcome.

## Before you start

- Search existing issues and pull requests to avoid duplicate work.
- For a substantial feature or architecture change, open a feature request first so the approach can be discussed before implementation.
- Keep each pull request focused on one change.
- Use English for issues, pull requests and code comments where possible. Clear examples are more useful than perfect wording.
- Be respectful and constructive when asking questions or reviewing contributions.

For vulnerabilities, follow [SECURITY.md](SECURITY.md). Do not publish security-sensitive details in a public issue or pull request.

## Reporting bugs and suggesting features

Use the [issue templates](https://github.com/triopsi/SplitNest/issues/new/choose). For a bug, include the SplitNest version, installation method, steps to reproduce, expected behavior and relevant error messages. For calculation issues, provide a small example with fictional amounts and participants.

Remove passwords, application keys, tokens and personal household data from logs, configuration snippets and screenshots. Never upload your full `.env` file or a real household backup.

For feature requests, describe the problem and the desired outcome from a user's perspective. Mention existing workarounds if helpful.

## Setting up development

### Fork first, then create a branch

Contributors should work in their own fork. Create a separate branch for each fix or feature and open a pull request from that branch to `triopsi/SplitNest:main`. Do not make your changes directly on `main`.

1. [Fork SplitNest on GitHub](https://github.com/triopsi/SplitNest/fork).
2. Clone **your fork**, replacing `YOUR-USERNAME` below with your GitHub username.
3. Add the official repository as `upstream` so you can keep your fork current.

```sh
git clone https://github.com/YOUR-USERNAME/SplitNest.git
cd SplitNest
git remote add upstream https://github.com/triopsi/SplitNest.git
git fetch upstream
git switch main
git merge --ff-only upstream/main
git switch -c fix/short-description
```

Choose a descriptive branch name, for example `fix/monthly-rounding` or `feat/expense-filter`. Start each new contribution from an up-to-date `main`.

If you already cloned the official repository, you can keep your working directory. After creating your fork, rename the existing remote and add your fork:

```sh
git remote rename origin upstream
git remote add origin https://github.com/YOUR-USERNAME/SplitNest.git
```

### Run the application

The recommended environment uses Docker, VS Code and its Dev Containers extension:

1. Open your cloned fork in VS Code and run **Dev Containers: Reopen in Container**.
2. The setup script installs Composer dependencies, creates the development application key, runs migrations and publishes Filament assets.
3. Open [localhost:8080/admin](http://localhost:8080/admin) and create the first administrator in the browser.

The root [compose.yaml](compose.yaml) is the PostgreSQL development stack. [compose.production.yaml](compose.production.yaml) runs a released image and is intended for deployment. See the [README](README.md#development) for more context.

For frontend changes, use Node.js 22 as in CI, install the locked dependencies with `npm ci`, then run `npm run build`. Run these commands in an environment with Node.js available. Rebuild assets after changing JavaScript, styles or templates that affect Tailwind output. Generated files in `public/build` are not committed.

```sh
npm ci
npm run build
```

Use `npm run dev` while working on the frontend to rebuild changes automatically. This project uses **npm and `package-lock.json`**, not Yarn. CI also checks the build with Node.js 24.

Use fictional data for development and testing.

## Code conventions

- Follow nearby code and reuse existing components before adding new ones.
- Check the installed package versions before relying on framework or package APIs.
- Keep money values as integer cents and preserve the existing allocation and rounding behavior.
- Keep existing installations compatible when changing database schemas, configuration or persistent storage. Explain any necessary migration or manual upgrade steps.
- Add meaningful regression coverage for behavior changes. Pure documentation, copy and styling changes do not require new tests.
- Use Laravel Pint for PHP formatting and include PHPDoc blocks for classes and methods in `app/` and `database/factories/`.
- Use translation keys for interface text and update both English and German translations when applicable.
- Discuss dependency additions or major dependency upgrades before including them.

## Checks before submitting

Run the affected tests while developing. Before submitting a code change, run the relevant checks from the application directory:

```sh
composer validate --strict --no-check-publish
php artisan test
vendor/bin/pint --dirty
php scripts/check-phpdocs.php
composer audit --locked --no-interaction
npm audit --package-lock-only --audit-level=high
```

For frontend changes, also run:

```sh
npm run build
```

The PHPUnit configuration uses in-memory SQLite, independently of the development database. Keep this isolation intact. Never point automated tests at a real household database.

Filament and Livewire tests are part of `tests/Feature`; they run in CI along with the other feature tests. When changing a form, table, action or permission, exercise the component through Livewire and assert the saved data or rejected operation. Follow the existing feature tests. Use a browser for layout checks and passkey authenticator interactions that a PHP feature test cannot exercise.

The development setup installs local pre-commit hooks when `pre-commit` is available. GitHub Actions runs these checks for pull requests:

| Check | What it verifies |
| --- | --- |
| Composer configuration | Valid manifest and matching lock file. |
| Pint and PHPDocs | PHP formatting and required documentation blocks. |
| Unit and feature tests, PHP 8.4 and 8.5 | Calculations, authentication, authorization, Filament forms/actions and other application behavior. |
| PostgreSQL 17 and MariaDB 11.4 migrations | A clean installation, rollback and reapplication of the schema on isolated CI databases. This does not run the full feature suite on those engines. |
| Frontend builds, Node.js 22 and 24 | Locked dependency installation and Vite asset compilation. |
| Production Docker build and startup | Image builds, first-run registration is reachable, and the application key survives a restart. |
| Composer and npm audits | Known dependency vulnerabilities; npm fails for high or critical findings. |
| Gitleaks | Accidentally committed credentials and other secrets. |
| actionlint | GitHub workflow syntax, expressions and embedded shell commands. |

Audits also run weekly and can be triggered manually from GitHub Actions. Audit jobs report findings; they do not update dependencies automatically. Do not suppress failures or run forced dependency upgrades just to make checks green.

Documentation-only contributions can describe their manual review instead of running unrelated application tests. Maintainers should configure required status checks and reviews for `main` in GitHub rulesets; adding a workflow alone does not prevent merging a failing PR. First-time fork contributions may need a maintainer to approve their workflow run.

## Opening a pull request

1. Commit your focused change on your contribution branch, then push it to **your fork**:

   ```sh
   git push -u origin fix/short-description
   ```

2. On GitHub, choose **Compare & pull request** (or **compare across forks**). Set the **base repository** to `triopsi/SplitNest`, **base branch** to `main`, and the **head repository and branch** to your fork and contribution branch. Fill out the [pull request template](.github/pull_request_template.md).
3. Describe the problem, resulting behavior and how you verified the change.
4. Link relevant existing issues. Omit issue references when none apply; do not leave example issue numbers in the description, including inside HTML comments.
5. Include screenshots for visible changes, using fictional data, and explain any upgrade requirements.
6. Wait for the checks and maintainer review. Address feedback by pushing more commits to the **same branch**; the existing PR updates automatically. Draft PRs are welcome for work in progress.

Use a Conventional Commit title, for example:

```text
fix: correct monthly contribution rounding
feat: add a yearly expense summary
docs: clarify Docker installation
```

The release automation reads commit messages on `main`. When using **Squash and merge**, ensure the final squash commit has the intended Conventional Commit title. Typically, `fix:` triggers a patch release, `feat:` triggers a minor release, and documentation or maintenance changes do not trigger a release by themselves. Clearly identify breaking changes before merging.

## Translations

Translations live in `lang/<locale>/`. For a new language, copy `lang/en`, translate the values and preserve all keys and placeholders such as `:name` and `{month}`. Add the language's native display name to `App\Support\Locales` when needed.

Run `php artisan test --compact tests/Feature/LocalizationTest.php` after translation changes. See [Translations in the README](README.md#translations) for more details.

## License

Contributions are made under the project's [MIT license](LICENCE).
