# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.5.9
ARG NODE_VERSION=22

# Compile the PHP extensions required by the locked application dependency set.
FROM php:${PHP_VERSION}-apache-bookworm AS php-extensions

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpq-dev; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        pcntl \
        pdo_pgsql; \
    pecl install redis-6.3.0; \
    docker-php-ext-enable redis; \
    rm -rf /var/lib/apt/lists/*

# Xdebug exists only in the development target.
FROM php-extensions AS php-development-extensions

RUN set -eux; \
    pecl install xdebug-3.5.3; \
    docker-php-ext-enable xdebug

FROM node:${NODE_VERSION}-bookworm-slim AS node

FROM composer:2 AS composer

# Runtime shared by the production web, queue-worker, and scheduler processes.
FROM php:${PHP_VERSION}-apache-bookworm AS runtime-base

# The pg_dump client must match the production PostgreSQL 18 server; Debian
# bookworm only ships PostgreSQL 15 client tools, so the PGDG apt repository
# is required for a compatible postgresql-client-18.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gnupg \
        libcurl4 \
        libonig5 \
        libpq5 \
        libxml2 \
        restic; \
    install -d /usr/share/postgresql-common/pgdg; \
    curl -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
        --fail https://www.postgresql.org/media/keys/ACCC4CF8.asc; \
    echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list; \
    apt-get update; \
    apt-get install -y --no-install-recommends postgresql-client-18; \
    apt-get purge -y --auto-remove ${PHPIZE_DEPS} gnupg; \
    rm -rf /var/lib/apt/lists/*

COPY --from=php-extensions /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-extensions /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

RUN set -eux; \
    cp "${PHP_INI_DIR}/php.ini-production" "${PHP_INI_DIR}/php.ini"; \
    a2enmod rewrite; \
    sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf; \
    mkdir -p /var/run/apache2 /var/lock/apache2 /var/log/apache2; \
    chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2

WORKDIR /var/www/html

EXPOSE 8080

# SIGTERM works for Apache, Laravel queue workers, and schedule:work.
STOPSIGNAL SIGTERM

# Local Docker runtime. Source, vendor, and node_modules are bind-mounted.
FROM runtime-base AS development

USER root

COPY --from=php-development-extensions /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-development-extensions /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules

ARG WWWUSER=1000
ARG WWWGROUP=1000

RUN set -eux; \
    cp "${PHP_INI_DIR}/php.ini-development" "${PHP_INI_DIR}/php.ini"; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip; \
    rm -rf /var/lib/apt/lists/*; \
    ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm; \
    ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx; \
    groupmod -o -g "${WWWGROUP}" www-data; \
    usermod -o -u "${WWWUSER}" -g "${WWWGROUP}" www-data; \
    chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2

ENV COMPOSER_HOME=/tmp/composer \
    NPM_CONFIG_CACHE=/tmp/npm-cache \
    XDEBUG_MODE=off

USER www-data

CMD ["apache2-foreground"]

# Build production Composer dependencies and immutable Vite assets.
FROM runtime-base AS build

USER root

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip; \
    rm -rf /var/lib/apt/lists/*; \
    ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm; \
    ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY composer.json composer.lock package.json package-lock.json .npmrc ./

RUN APP_ENV=local COMPOSER_ALLOW_SUPERUSER=1 \
    composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

RUN npm ci

COPY . .

RUN set -eux; \
    mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache; \
    APP_ENV=local APP_DEBUG=false COMPOSER_ALLOW_SUPERUSER=1 \
        composer dump-autoload \
            --no-dev \
            --classmap-authoritative \
            --no-interaction; \
    APP_ENV=local APP_DEBUG=false php artisan route:clear; \
    APP_ENV=local APP_DEBUG=false npm run build; \
    composer check-platform-reqs --no-dev; \
    rm -rf \
        node_modules \
        resources/js/actions \
        resources/js/routes \
        resources/js/wayfinder \
        public/hot

# Immutable production runtime. No Composer, Node, npm, Xdebug, source mounts,
# development dependencies, compiler toolchain, or environment file.
FROM runtime-base AS production

USER root

COPY docker/php/production.ini ${PHP_INI_DIR}/conf.d/zz-production.ini
COPY --from=build /var/www/html /var/www/html

RUN set -eux; \
    mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache; \
    rm -f public/hot; \
    chown -R www-data:www-data storage bootstrap/cache; \
    find storage bootstrap/cache -type d -exec chmod 775 {} +; \
    find storage bootstrap/cache -type f -exec chmod 664 {} +

USER www-data

CMD ["apache2-foreground"]
