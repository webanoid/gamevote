#!/bin/sh
# Runs before nginx starts: from /docker-entrypoint.d/ in the docker-compose nginx image,
# and from railway-start.sh in the Railway image.
# When HTTP_AUTH_PASSWORD is set, protect the whole site with HTTP basic auth;
# otherwise leave it open (the default, so `docker-compose up` works without setup).
set -eu

snippet=/etc/nginx/snippets/http-auth.conf
password_file=/etc/nginx/.htpasswd
mkdir -p /etc/nginx/snippets

if [ -z "${HTTP_AUTH_PASSWORD:-}" ]; then
    : > "$snippet"
    rm -f "$password_file"
    echo "$0: HTTP_AUTH_PASSWORD is not set; HTTP basic auth is off"
    exit 0
fi

user="${HTTP_AUTH_USER:-gamevote}"
case "$user" in
    *:*) echo "$0: HTTP_AUTH_USER must not contain ':'" >&2; exit 1 ;;
esac

# Hash with SHA-512 crypt, reading the password from stdin so it never appears in a process list.
# The Railway image has PHP; the Alpine nginx image has BusyBox mkpasswd.
hash_password() {
    if command -v php >/dev/null 2>&1; then
        php -r 'echo crypt(stream_get_contents(STDIN), "\$6\$" . bin2hex(random_bytes(8)));'
    else
        mkpasswd -m sha512 -P 0
    fi
}

hash=$(printf '%s' "$HTTP_AUTH_PASSWORD" | hash_password)
printf '%s:%s\n' "$user" "$hash" > "$password_file"

# nginx workers run as "nginx" on Alpine and "www-data" on Debian.
group=$(id -gn nginx 2>/dev/null || echo www-data)
chown "root:$group" "$password_file"
chmod 640 "$password_file"

# $basic_auth_realm is "off" for /up (see nginx.conf), so health checks never need the password.
cat > "$snippet" <<'CONF'
auth_basic $basic_auth_realm;
auth_basic_user_file /etc/nginx/.htpasswd;
CONF

echo "$0: HTTP basic auth is on for user '$user'"
