<?php

return [

    'host' => env('MAIL_HOST', 'smtp.gmail.com'),

    'port' => (int) env('MAIL_PORT', 587),

    'username' => env('MAIL_USERNAME'),

    'password' => env('MAIL_PASSWORD'),

    'encryption' => env('MAIL_ENCRYPTION', 'tls'),

    'ehlo_domain' => env('MAIL_EHLO_DOMAIN'),

    'from_address' => env('MAIL_FROM_ADDRESS'),

    'from_name' => env('MAIL_FROM_NAME', env('APP_NAME', 'ANI-FAMS')),

    'otp_ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),

];
