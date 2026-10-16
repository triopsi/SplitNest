# Contributing to SplitNest

Thanks for helping improve SplitNest! Contributions of all sizes are welcome, including bug reports, documentation improvements, translations, tests and code.

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

The recommended environment uses Docker, VS Code and its Dev Containers extension:

1. Fork the repository and clone your fork.
2. Open the project in VS Code and run **Dev Containers: Reopen in Container**.
3. The setup script installs Composer dependencies, creates the development application key, runs migrations and publishes Filament assets.
4. Open [localhost:8080/admin](http://localhost:8080/admin) and create the first administrator in the browser.
5. Create a branch from the current `main` for your change.

The root [compose.yaml](compose.yaml) is the PostgreSQL development stack. [compose.production.yaml](compose.production.yaml) runs a released image and is intended for deployment. See the [README](README.md#development) for more context.

For frontend changes, use Node.js 22 as in CI, install the locked dependencies with `npm ci`, then run `npm run build`. Run these commands in an environment with Node.js available. Rebuild assets after changing JavaScript, styles or templates that affect Tailwind output. Generated files in `public/build` are not committed.

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
php artisan test
vendor/bin/pint --dirty
php scripts/check-phpdocs.php
```

For frontend changes, also run:

```sh
npm run build
```

The PHPUnit configuration uses in-memory SQLite, independently of the development database. Keep this isolation intact. Never point automated tests at a real household database.

The development setup installs local pre-commit hooks when `pre-commit` is available. GitHub Actions also checks code style, PHPDocs, tests, secrets and the production build. Documentation-only contributions can describe their manual review instead of running unrelated application tests.

## Opening a pull request

1. Target `main` and fill out the [pull request template](.github/pull_request_template.md).
2. Describe the problem, resulting behavior and how you verified the change.
3. Link relevant existing issues. Omit issue references when none apply; do not leave example issue numbers in the description, including inside HTML comments.
4. Include screenshots for visible changes, using fictional data, and explain any upgrade requirements.
5. Keep the PR up to date and address review feedback. Draft PRs are welcome for work in progress.

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
