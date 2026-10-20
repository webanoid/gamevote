# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.5
ARG NODE_VERSION=22
ARG NGINX_VERSION=1.30

FROM node:${NODE_VERSION}-trixie-slim AS node

# ---------------------------------------------------------------------------
# PHP 8.5 FPM with the extensions Laravel needs (SQLite and OPcache are built in)
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev sqlite3 \
    && docker-php-ext-install -j"$(nproc)" intl zip pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# PHP + Node toolchain (Wayfinder's Vite plugin shells out to artisan)
# ---------------------------------------------------------------------------
FROM php-base AS toolchain

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# ---------------------------------------------------------------------------
# Development: source is bind-mounted, see docker-compose.yml
# ---------------------------------------------------------------------------
FROM toolchain AS dev

ARG UID=1000
ARG GID=1000

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && mkdir -p /var/www/html/node_modules \
    && chown "$UID:$GID" /var/www/html/node_modules

ENV HOME=/tmp COMPOSER_HOME=/tmp/composer

COPY docker/dev-entrypoint.sh /usr/local/bin/dev-entrypoint
ENTRYPOINT ["dev-entrypoint"]
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=8000 & exec npm run dev"]

# ---------------------------------------------------------------------------
# Build: install production dependencies and compile front-end assets
# ---------------------------------------------------------------------------
FROM toolchain AS build

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && npm run build \
    && rm -rf node_modules

# ---------------------------------------------------------------------------
# Production PHP-FPM image: no Node, no dev dependencies
# ---------------------------------------------------------------------------
FROM php-base AS production

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

# /data holds SQLite and the generated keys. Mount persistent storage there: the sqlite-data
# volume in docker-compose.yml, or a Railway volume (Railway rejects the VOLUME instruction).
RUN mkdir -p /data && chown www-data:www-data /data

EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# nginx: serves public/ (including built assets) and proxies PHP to the app
# ---------------------------------------------------------------------------
FROM nginx:${NGINX_VERSION}-alpine AS web

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --chmod=755 docker/nginx-http-auth.sh /docker-entrypoint.d/40-http-auth.sh
COPY --from=build /var/www/html/public /var/www/html/public

EXPOSE 80

# ---------------------------------------------------------------------------
# Railway: nginx and PHP-FPM in one container. Railway runs one container per
# service and always builds the last stage of this file; docker-compose builds
# the web and app targets above instead.
# ---------------------------------------------------------------------------
FROM production AS railway

RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
RUN sed -i 's/app:9000/127.0.0.1:9000/g' /etc/nginx/conf.d/default.conf \
    && printf '[www]\nlisten = 127.0.0.1:9000\n' > /usr/local/etc/php-fpm.d/zz-railway.conf

COPY --chmod=755 docker/nginx-http-auth.sh /usr/local/bin/nginx-http-auth
COPY --chmod=755 docker/railway-start.sh /usr/local/bin/railway-start

# The PHP image stops with SIGQUIT; Railway (and this script) use SIGTERM.
STOPSIGNAL SIGTERM
EXPOSE 8080
ENTRYPOINT ["railway-start"]
CMD []
