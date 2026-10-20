#!/bin/sh
set -e

cd /var/www/html

DB_DATABASE="${DB_DATABASE:-/data/database.sqlite}"
export DB_DATABASE

# Generate and persist an APP_KEY on first boot if one wasn't provided.
if [ -z "$APP_KEY" ]; then
    if [ ! -f /data/app.key ]; then
        php artisan key:generate --show > /data/app.key
        chown www-data:www-data /data/app.key
    fi
    APP_KEY="$(cat /data/app.key)"
    export APP_KEY
fi

if [ ! -f "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
fi
chown www-data:www-data "$DB_DATABASE" "$(dirname "$DB_DATABASE")"

php artisan migrate --force
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
