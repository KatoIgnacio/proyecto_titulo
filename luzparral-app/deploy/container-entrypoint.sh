#!/bin/sh
set -eu
umask 007

if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY no está configurada." >&2
    exit 1
fi

mkdir -p \
    bootstrap/cache \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

chown -R www-data:www-data bootstrap/cache storage
chmod -R u=rwX,g=rwX,o= bootstrap/cache storage

php artisan package:discover --ansi

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Artisan se ejecuta como root durante el arranque y crea los archivos de cache
# con ese propietario. Restablecer propiedad y modos permite que los workers de
# Apache (www-data) los lean y mantengan sin exponerlos a otros usuarios.
chown -R www-data:www-data bootstrap/cache storage
chmod -R u=rwX,g=rwX,o= bootstrap/cache storage

exec "$@"
