# Toolchain image for Leash: PHP 8.4 CLI + Composer. The host needs only Docker.
FROM php:8.4-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev sqlite3 \
 && docker-php-ext-install intl zip pcntl \
 && rm -rf /var/lib/apt/lists/*

COPY leash.ini /usr/local/etc/php/conf.d/leash.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /app
