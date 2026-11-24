# REOS development container: PHP 8.5 with the extensions production uses,
# Composer 2, and Node 22 + pnpm (added by devcontainer features).
FROM php:8.5-cli

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions pdo_pgsql pgsql redis intl bcmath zip pcntl opcache exif gd \
 && apt-get update \
 && apt-get install -y --no-install-recommends git unzip curl postgresql-client ffmpeg \
 && rm -rf /var/lib/apt/lists/*

ENV COMPOSER_HOME=/home/vscode/.composer
