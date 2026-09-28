#!/usr/bin/env bash
set -e

echo "Installing production Composer dependencies..."
composer install --no-dev --optimize-autoloader --working-dir=/var/www/html

echo "Preparing Laravel storage..."
mkdir -p /var/www/html/storage/framework/cache \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

if [ -n "${APP_KEY:-}" ]; then
  echo "Caching Laravel configuration..."
  php artisan config:cache
  echo "Caching Laravel routes..."
  php artisan route:cache
  echo "Running database migrations..."
  php artisan migrate --force
else
  echo "APP_KEY is not set; skipping Laravel cache and migration tasks."
fi
