#!/bin/sh
set -e

: "${PORT:=10000}"

# Render assigns $PORT dynamically; Apache must listen on it.
echo "Listen ${PORT}" > /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Cache at start-up (never at build time) so runtime env vars are honoured.
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

exec apache2-foreground
