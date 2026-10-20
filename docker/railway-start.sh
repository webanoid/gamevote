#!/bin/bash
# Railway runs one container per service, so this image runs PHP-FPM and nginx together
# (docker-compose runs them as separate web and app containers instead).
set -euo pipefail

# Same first-boot setup as the docker-compose app container: APP_KEY, SQLite file,
# migrations and caches. Its final `exec "$@"` runs `true`, handing control back here.
entrypoint true
nginx-http-auth

# Listen on Railway's PORT (default 8080) so its proxy can route to us.
port="${PORT:-8080}"
sed -i -E "s/listen (\[::\]:)?[0-9]+;/listen \1${port};/" /etc/nginx/conf.d/default.conf

php-fpm --nodaemonize &
fpm_pid=$!
nginx -g 'daemon off;' &
nginx_pid=$!

# QUIT is the graceful stop for both: requests already in progress finish first.
stop() {
    kill -QUIT "$fpm_pid" "$nginx_pid" 2>/dev/null || true
    wait "$fpm_pid" "$nginx_pid" 2>/dev/null || true
}

# Stop both on shutdown; if either exits on its own, stop the other so Railway restarts us.
trap 'stop; exit 0' TERM INT QUIT
status=0
wait -n "$fpm_pid" "$nginx_pid" || status=$?
stop
exit "$status"
