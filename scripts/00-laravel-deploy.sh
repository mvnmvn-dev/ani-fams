#!/usr/bin/env bash
# Runs before nginx/php-fpm start (richarvey RUN_SCRIPTS).
set -u

cd /var/www/html

echo "Preparing Laravel storage..."
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
touch storage/logs/laravel.log
chown -R nginx:nginx storage bootstrap/cache 2>/dev/null \
  || chown -R www-data:www-data storage bootstrap/cache 2>/dev/null \
  || true
chmod -R ug+rwX storage bootstrap/cache || true

if [ -z "${APP_KEY:-}" ]; then
  echo "ERROR: APP_KEY is not set; cannot safely bootstrap Laravel or run migrations."
  exit 1
fi

echo "Caching Laravel configuration..."
php artisan config:cache || echo "Warning: config:cache failed (continuing)."
echo "Caching Laravel routes..."
php artisan route:cache || echo "Warning: route:cache failed (continuing)."

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "Running all pending Laravel migrations..."
  php artisan migrate --force || {
    echo "ERROR: Laravel migrations failed. Refusing to start with a potentially incomplete schema."
    exit 1
  }
else
  echo "Applying the configured additive migrations only..."
fi

run_required_migration() {
  migration_path="$1"
  echo "Applying required migration: ${migration_path}"
  php artisan migrate --force --path="${migration_path}" || {
    echo "ERROR: Required migration failed: ${migration_path}"
    exit 1
  }
}

run_required_migration database/migrations/2026_09_29_000001_defense_ready_improvements.php
run_required_migration database/migrations/2026_10_02_000001_set_admin_otp_email.php
run_required_migration database/migrations/2026_10_03_000001_restore_admin_otp_email.php
run_required_migration database/migrations/2026_10_03_000002_disable_admin_two_factor.php
run_required_migration database/migrations/2026_11_07_000001_group8_integration_fields.php
run_required_migration database/migrations/2026_11_09_000001_integration_foundation.php
run_required_migration database/migrations/2026_11_11_000001_ensure_visitor_integration_columns.php

echo "Required schema migrations completed."
exit 0
