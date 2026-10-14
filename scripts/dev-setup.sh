#!/bin/sh
set -eu
composer install --no-interaction --prefer-dist
if [ ! -f .env ]; then cp .env.example .env; fi
if ! grep -q '^APP_KEY=base64:' .env; then php artisan key:generate --no-interaction; fi
# Pre-Commit-Hooks aktivieren (Konfiguration: .pre-commit-config.yaml).
# Ein evtl. zuvor gesetzter core.hooksPath wird entfernt, damit .git/hooks greift.
if [ -d .git ] && command -v pre-commit >/dev/null 2>&1; then
    git config --unset-all core.hooksPath || true
    pre-commit install --overwrite
fi
# SSH-Zugangsdaten vom Host uebernehmen (Readonly-Mount /ssh-host, siehe
# .devcontainer/devcontainer.json). Kopie statt direkter Nutzung, weil ssh
# verlangt, dass Schluessel dem aufrufenden Benutzer gehoeren (hier: root).
if [ -d /ssh-host ] && [ ! -d /root/.ssh ]; then
    cp -r /ssh-host /root/.ssh
    chmod 700 /root/.ssh
    find /root/.ssh -type f -exec chmod 600 {} +
fi
# GitHub-Hostkeys global hinterlegen, damit kein Bestaetigungs-Prompt kommt.
if command -v ssh-keyscan >/dev/null 2>&1 && ! grep -q '^github.com' /etc/ssh/ssh_known_hosts 2>/dev/null; then
    ssh-keyscan -t ed25519,ecdsa,rsa github.com >> /etc/ssh/ssh_known_hosts 2>/dev/null || true
fi
# Bind mounts need to remain writable for both the editor and Apache in development.
chmod -R a+rwX storage bootstrap/cache
php artisan migrate --no-interaction
php artisan filament:assets
echo 'Bereit. Login erstellen: php artisan splitnest:user'
