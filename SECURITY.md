# Security Policy

## Supported versions

Security fixes target the latest stable SplitNest release. Older releases are not maintained as separate security branches; please upgrade to the latest stable release when a fix becomes available.

Development branches and prereleases may contain incomplete changes and are not recommended for production use.

## Reporting a vulnerability

Please report suspected vulnerabilities privately. Do not include vulnerability details, exploit code or sensitive data in a public issue or pull request.

Use GitHub's private vulnerability reporting:

1. Open [SplitNest's Security Advisories page](https://github.com/triopsi/SplitNest/security/advisories).
2. Select **Report a vulnerability**, if available.
3. Submit the details using the private reporting form.

If private reporting is unavailable, open an [issue](https://github.com/triopsi/SplitNest/issues/new) asking the maintainer to enable private reporting or provide a private contact method. Keep that request generic and do not disclose the vulnerability or affected installation details publicly.

Ordinary bugs and feature requests belong in the [issue tracker](https://github.com/triopsi/SplitNest/issues/new/choose). If you are unsure whether a problem is security-related, use the private reporting route first.

## What to include

A useful private report includes:

- A short description of the vulnerability and its potential impact.
- The affected SplitNest version or commit and installation method.
- Relevant environment details, such as database type, reverse proxy setup or browser version.
- Reproduction steps or a minimal proof of concept using fictional data.
- Any authentication, permissions or configuration required to reproduce it.
- Suggested mitigations or a proposed fix, if you have them.

Do not send real passwords, application keys, access tokens or household backups. Redact personal information and credentials from logs and screenshots. Only test systems you own or are authorized to assess.

## Handling and disclosure

SplitNest is maintained on a best-effort basis. Response and fix times depend on maintainer availability and the severity and complexity of the report; no fixed response time is guaranteed.

The maintainer will review the report, request additional information when needed and coordinate a fix and disclosure with the reporter. Please allow time for investigation and remediation before publishing technical details.

When a fix is released, affected versions, upgrade instructions and relevant mitigations can be documented in release notes or a GitHub Security Advisory. Reporter credit can be included with the reporter's agreement.

## Updating an installation

Review the [release notes](https://github.com/triopsi/SplitNest/releases) and the [update instructions](README.md#updates). Back up your data before upgrading, retain your application key and persistent volumes, and apply any documented migration steps. If you pin a Docker image version, update the tag to the fixed release rather than pulling the same old tag.
