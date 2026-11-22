# REOS preview image (P7.0): one image for the web app, queue worker,
# scheduler and Reverb. Built from the repository root:
#   docker build -f deploy/preview/Dockerfile -t reos-preview .
# Not the production build (AWS, ADR 0004); only for the private preview.

FROM dunglas/frankenphp:1-php8.5-bookworm AS base
RUN install-php-extensions pdo_pgsql pgsql redis intl zip gd bcmath pcntl exif opcache sockets \
 && apt-get update \
 && apt-get install -y --no-install-recommends poppler-utils postgresql-client \
 && rm -rf /var/lib/apt/lists/*
WORKDIR /app

# ── Build: PHP dependencies, Wayfinder routes and the web assets ───────────
FROM base AS build
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends curl ca-certificates git unzip \
 && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
 && apt-get install -y --no-install-recommends nodejs \
 && corepack enable \
 && rm -rf /var/lib/apt/lists/*

# Browsers connect to Reverb at the public host (baked into the web assets).
# The key is Reverb's public app key, not a secret (the secret stays in .env).
ARG VITE_REVERB_APP_KEY=reos-preview-key
ARG VITE_REVERB_HOST=preview.reos.com
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

COPY . /src
WORKDIR /src/apps/platform
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader \
 && cd /src && git init -q \
 && pnpm install --frozen-lockfile --filter platform... \
 && cd /src/apps/platform \
 && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && cp .env.example .env && php artisan key:generate --no-interaction \
 && php artisan wayfinder:generate --with-form \
 && pnpm build \
 && rm -rf node_modules .env .git /src/.git storage/logs/* bootstrap/cache/*.php

# ── Runtime ────────────────────────────────────────────────────────────────
FROM base AS runtime
ENV APP_ENV=preview \
    OCTANE_SERVER=frankenphp \
    PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"
COPY deploy/preview/php.ini $PHP_INI_DIR/app.conf.d/reos.ini
COPY --from=build /src/apps/platform /app
COPY deploy/preview/bin/entrypoint.sh /usr/local/bin/reos-entrypoint
RUN chmod +x /usr/local/bin/reos-entrypoint \
 && mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
 && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 8000
ENTRYPOINT ["reos-entrypoint"]
CMD ["web"]
