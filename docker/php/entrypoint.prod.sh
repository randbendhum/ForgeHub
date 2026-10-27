#!/bin/sh
set -e

mkdir -p \
    /var/www/html/storage/app/mods \
    /var/www/html/storage/app/mods/_quarantine \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[ForgeModHub] Waiting for MySQL database..."
    tries=0
    until php artisan db:monitor --databases=mysql >/dev/null 2>&1 || [ $tries -ge 30 ]; do
        tries=$((tries + 1))
        sleep 2
    done

    echo "[ForgeModHub] Running database migrations and base seeders..."
    php artisan migrate --force
    php artisan db:seed --class=Database\\Seeders\\GameSeeder --force
    php artisan db:seed --class=Database\\Seeders\\TagSeeder --force

    if [ "${RUN_DEMO_SEEDER:-false}" = "true" ]; then
        php artisan db:seed --class=Database\\Seeders\\DemoSeeder --force
    fi
fi

echo "[ForgeModHub] Caching configuration, routes, and views for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
