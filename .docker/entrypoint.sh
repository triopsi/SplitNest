#!/bin/sh
set -eu

APP_KEY="$(php /var/www/html/.docker/initialize.php)"
export APP_KEY

if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
    php /var/www/html/artisan migrate --force --no-interaction
fi

exec docker-php-entrypoint "$@"
