#!/bin/sh
set -e

cd /var/www/html

[ -f .env ] || cp .env.example .env
[ -d vendor ] || composer install --no-interaction
[ -f node_modules/.package-lock.json ] || npm ci
grep -q '^APP_KEY=base64:' .env || php artisan key:generate
[ -f database/database.sqlite ] || touch database/database.sqlite
php artisan migrate --force

exec "$@"
