# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.5
ARG NODE_VERSION=22

FROM node:${NODE_VERSION}-trixie-slim AS node

# ---------------------------------------------------------------------------
# PHP 8.5 + Apache with the extensions Laravel needs (SQLite and OPcache are built in)
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-apache AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev sqlite3 \
    && docker-php-ext-install -j"$(nproc)" intl zip pcntl \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite headers

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
# Production image: no Node, no dev dependencies
# ---------------------------------------------------------------------------
FROM php-base AS production

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN mkdir -p /data && chown www-data:www-data /data
VOLUME ["/data"]

EXPOSE 80
ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
