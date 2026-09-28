#!/usr/bin/env bash
set -e

cd /var/www/html

echo "Preparing Laravel storage..."
mkdir -p storage/framework/cache \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs
chown -R www-data:www-data storage bootstrap/cache || true

if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY is not set; skipping Laravel cache and migration tasks."
  exit 0
fi

echo "Caching Laravel configuration..."
php artisan config:cache

echo "Caching Laravel routes..."
php artisan route:cache

echo "Running database migrations..."
php artisan migrate --force
