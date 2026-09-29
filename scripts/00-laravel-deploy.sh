#!/usr/bin/env bash
set -e

cd /var/www/html

echo "Preparing Laravel storage..."
mkdir -p storage/framework/cache \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs

touch storage/logs/laravel.log

chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true


if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY is not set; skipping Laravel cache and migration tasks."
  exit 0
fi

echo "Caching Laravel configuration..."
php artisan config:cache

echo "Caching Laravel routes..."
php artisan route:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "Running database migrations..."
  php artisan migrate --force
else
  echo "Skipping full Laravel migrations; using the imported PostgreSQL schema."
fi

# Always apply additive Defense Ready schema (safe / idempotent) so QR check-in
# and related columns exist even when RUN_MIGRATIONS is false.
echo "Applying Defense Ready schema updates..."
php artisan migrate --force --path=database/migrations/2026_09_29_000001_defense_ready_improvements.php || {
  echo "Warning: Defense Ready migration did not complete. Approve/QR features may be limited."
}
