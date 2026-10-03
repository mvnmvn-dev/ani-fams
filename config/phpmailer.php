<?php

return [

    // auto = Resend HTTPS when RESEND_API_KEY is set, otherwise SMTP.
    // Use "resend" or "smtp" to force one transport.
    'driver' => env('MAIL_OTP_DRIVER', 'auto'),

    'host' => env('MAIL_HOST', 'smtp.gmail.com'),

    'port' => (int) env('MAIL_PORT', 587),

    'username' => env('MAIL_USERNAME'),

    'password' => env('MAIL_PASSWORD'),

    'encryption' => env('MAIL_ENCRYPTION', 'tls'),

    'ehlo_domain' => env('MAIL_EHLO_DOMAIN'),

    // Resend requires a verified sender. Use onboarding@resend.dev for testing (delivers only
    // to the email on your Resend account). Override with your domain after verification.
    'from_address' => env('MAIL_FROM_ADDRESS', env('RESEND_API_KEY') ? 'onboarding@resend.dev' : null),

    'from_name' => env('MAIL_FROM_NAME', env('APP_NAME', 'ANI-FAMS')),

    // Keep well under Render's gateway limit so SMTP hangs return a flash error, not 504.
    'timeout' => (int) env('MAIL_TIMEOUT', 8),

    // Set false on Render when email delivery is unavailable so staff/employee can log in.
    'email_otp_enabled' => filter_var(env('AUTH_EMAIL_OTP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    // Used when an admin has no otp_email and a non-deliverable (e.g. *.local) login email.
    // Prefer ADMIN_OTP_EMAIL; otherwise code falls back to MAIL_FROM_ADDRESS / MAIL_USERNAME.
    'admin_otp_email' => env('ADMIN_OTP_EMAIL'),

    'otp_ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),

];
