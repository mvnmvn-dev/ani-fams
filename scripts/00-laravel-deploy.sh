#!/usr/bin/env bash
# Runs before nginx/php-fpm start (richarvey RUN_SCRIPTS). Keep this fast and non-fatal.
set -u

cd /var/www/html

echo "Preparing Laravel storage..."
mkdir -p storage/framework/cache \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs

touch storage/logs/laravel.log

# Image runs php-fpm as nginx; www-data may not exist on Alpine.
chown -R nginx:nginx storage bootstrap/cache 2>/dev/null \
  || chown -R www-data:www-data storage bootstrap/cache 2>/dev/null \
  || true
chmod -R ug+rwX storage bootstrap/cache || true

if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY is not set; skipping Laravel cache and migration tasks."
  exit 0
fi

echo "Caching Laravel configuration..."
php artisan config:cache || echo "Warning: config:cache failed (continuing)."

echo "Caching Laravel routes..."
php artisan route:cache || echo "Warning: route:cache failed (continuing)."

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "Running database migrations..."
  php artisan migrate --force || echo "Warning: migrate failed (continuing)."
else
  echo "Skipping full Laravel migrations; using the imported PostgreSQL schema."
fi

# Always apply additive Defense Ready schema (safe / idempotent) so QR check-in
# and related columns exist even when RUN_MIGRATIONS is false.
echo "Applying Defense Ready schema updates..."
php artisan migrate --force --path=database/migrations/2026_09_29_000001_defense_ready_improvements.php || {
  echo "Warning: Defense Ready migration did not complete. Approve/QR features may be limited."
}

echo "Applying admin OTP email update..."
php artisan migrate --force --path=database/migrations/2026_10_02_000001_set_admin_otp_email.php || {
  echo "Warning: Admin OTP email migration did not complete."
}

echo "Restoring missing admin OTP email..."
php artisan migrate --force --path=database/migrations/2026_10_03_000001_restore_admin_otp_email.php || {
  echo "Warning: Admin OTP restore migration did not complete."
}

echo "Disabling admin authenticator 2FA (email OTP fallback)..."
php artisan migrate --force --path=database/migrations/2026_10_03_000002_disable_admin_two_factor.php || {
  echo "Warning: Admin 2FA disable migration did not complete."
}

exit 0
