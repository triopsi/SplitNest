# =============================================================================
# SplitNest – Multi-Stage-Image
#
#   base        PHP 8.4 + Apache: gemeinsame Basis für alle Varianten
#   development Ziel für den Devcontainer (compose.yaml, target: development)
#   frontend    Node 22: baut die Vite-Assets (public/build)
#   production  Schlankes Produktions-Image (Standard-Target für Release-Builds)
# =============================================================================

# -----------------------------------------------------------------------------
# Stage: base – Laufzeit-Basis (PHP 8.4 + Apache auf Debian Bookworm)
# -----------------------------------------------------------------------------
FROM php:8.4-apache-bookworm AS base

# -- Systempakete -------------------------------------------------------------
# Laufzeit- und Header-Bibliotheken für die PHP-Extensions (alphabetisch),
# dazu: curl (Healthcheck), git + unzip (Composer holt Pakete aus Quellen).
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libonig-dev \
    libpng-dev \
    libpq-dev \
    libsqlite3-dev \
    libzip-dev \
    unzip \
    # gd mit FreeType- und JPEG-Unterstützung konfigurieren (PDF/Thumbnails)
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    # -- PHP-Extensions -------------------------------------------------------
    # bcmath     exakte Dezimalrechnung (Geldbeträge)
    # gd         Bildverarbeitung
    # intl       Lokalisierung/Formatierung (Filament-Anforderung)
    # mbstring   Multibyte-Strings
    # opcache    Bytecode-Cache (Performance)
    # pdo_pgsql  PostgreSQL-Anbindung (Entwicklung/Produktion)
    # pdo_sqlite SQLite-Anbindung (Tests, lokale Nutzung)
    # zip        ZIP-Archive
    && docker-php-ext-install -j"$(nproc)" \
    bcmath \
    gd \
    intl \
    mbstring \
    opcache \
    pdo_pgsql \
    pdo_sqlite \
    zip \
    # -- Apache ---------------------------------------------------------------
    # rewrite: Laravel-Routing (public/index.php), headers: Sicherheits-Header
    && a2enmod rewrite headers \
    # Apache auf Port 8080 statt 80, damit der Container ohne root auskommt
    && sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    # apt-Listen entsorgen -> kleineres Image
    && rm -rf /var/lib/apt/lists/*

# Composer-Binary aus dem offiziellen Composer-Image übernehmen
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Apache-VHost mit DocumentRoot auf public/ (siehe .docker/apache.conf)
COPY .docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# App lauscht auf 8080 (compose mappt 127.0.0.1:${APP_PORT:-8080} -> 8080)
EXPOSE 8080

# -----------------------------------------------------------------------------
# Stage: development – Devcontainer-Ziel
# Der Code kommt per Bind-Mount in den Container, Abhängigkeiten installiert
# scripts/dev-setup.sh (postCreateCommand). Hier nur Composer als root erlauben.
# -----------------------------------------------------------------------------
FROM base AS development
ENV COMPOSER_ALLOW_SUPERUSER=1

# -- Werkzeuge für Git und die Pre-Commit-Hooks --------------------------------
# openssh-client: ssh/ssh-keyscan für git per SSH (Host-Keys kommen per
# Readonly-Mount in den Container, siehe .devcontainer/devcontainer.json)
# python3 + pre-commit: Hook-Framework (Debian-Pakete, kein pip nötig).
# gitleaks: Secret-Scanner als Binary aus den GitHub-Releases; die Asset-Namen
# nutzen "x64" statt "amd64", daher wird die Architektur gemappt.
ARG GITLEAKS_VERSION=8.28.0
RUN apt-get update && apt-get install -y --no-install-recommends \
    openssh-client \
    pre-commit \
    python3 \
    && rm -rf /var/lib/apt/lists/* \
    && arch="$(dpkg --print-architecture)" \
    && if [ "$arch" = "amd64" ]; then arch="x64"; fi \
    && curl -fsSL "https://github.com/gitleaks/gitleaks/releases/download/v${GITLEAKS_VERSION}/gitleaks_${GITLEAKS_VERSION}_linux_${arch}.tar.gz" \
    | tar -xz -C /usr/local/bin gitleaks

# -----------------------------------------------------------------------------
# Stage: frontend – baut die Vite-Assets (Tailwind) nach /app/public/build
# -----------------------------------------------------------------------------
FROM node:22-bookworm-slim AS frontend
WORKDIR /app

# Erst nur das Manifest kopieren -> npm-Layer bleibt gecacht, solange sich
# package.json nicht ändert (das Projekt hat keine Lock-Datei).
COPY package.json ./
RUN --mount=type=cache,target=/root/.npm \
    npm install --no-audit --no-fund

# Vite-Config + Quellen; Tailwind scannt dabei die Blade-Views in resources/views
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# -----------------------------------------------------------------------------
# Stage: production – vollständiges Produktions-Image
# -----------------------------------------------------------------------------
FROM base AS production
ENV APP_ENV=production \
    APP_DEBUG=false \
    COMPOSER_ALLOW_SUPERUSER=1

# -- PHP-Produktions-Einstellungen --------------------------------------------
# php.ini-production: u. a. display_errors=Off. Danach OPcache für gebakten
# Code optimieren (validate_timestamps=0: Code liegt unveränderlich im Image).
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.memory_consumption=192'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    } > "$PHP_INI_DIR/conf.d/zz-opcache.ini"

# -- PHP-Abhängigkeiten ---------------------------------------------------------
# Erst nur composer.json/lock kopieren -> dieser Layer wird nur bei geänderten
# Abhängigkeiten neu gebaut. --no-dev: keine Test-/Dev-Pakete im Image.
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# -- App-Code + Assets ----------------------------------------------------------
# .dockerignore hält vendor/, node_modules/, Tests und Logs aus dem Kontext.
COPY . .
# Gebaute Vite-Assets aus der frontend-Stage (public/build)
COPY --from=frontend /app/public/build ./public/build

# -- Verzeichnisse, Autoloader, Rechte ------------------------------------------
# Schreibbare Laravel-Verzeichnisse anlegen, optimierten Autoloader erzeugen
# (publiziert dabei auch die Filament-Assets), Rechte für www-data setzen.
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --no-dev --optimize \
    && chown -R www-data:www-data storage bootstrap/cache /var/run/apache2 /var/lock/apache2

# Apache/PHP laufen als www-data, nicht als root
USER www-data

# Liveness-Probe: Laravels Health-Route /up muss HTTP 200 liefern
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s \
     CMD curl -fsS http://localhost:8080/up || exit 1
