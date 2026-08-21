#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ ! -f .env ]; then
    cp .env.example .env
fi

composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress

chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

exec "$@"
