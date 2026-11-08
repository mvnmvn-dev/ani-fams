<?php

return [
    'system_user_email' => env('INTEGRATION_SYSTEM_USER_EMAIL', 'integration@system.local'),
    'auto_approve_sources' => array_values(array_filter(array_map('trim', explode(',', (string) env('INTEGRATION_AUTO_APPROVE_SOURCES', ''))))),
    'systems' => [
        'hr1' => [
            'base_url' => env('INTEGRATION_HR1_BASE_URL'),
            'token' => env('INTEGRATION_HR1_TOKEN'),
            'callback_url' => env('INTEGRATION_HR1_CALLBACK_URL'),
        ],
        'hr2' => [
            'base_url' => env('INTEGRATION_HR2_BASE_URL'),
            'token' => env('INTEGRATION_HR2_TOKEN'),
        ],
        'hr3' => [
            'base_url' => env('INTEGRATION_HR3_BASE_URL'),
            'token' => env('INTEGRATION_HR3_TOKEN'),
        ],
        'hr4' => [
            'base_url' => env('INTEGRATION_HR4_BASE_URL'),
            'token' => env('INTEGRATION_HR4_TOKEN'),
        ],
        'fin' => [
            'callback_url' => env('INTEGRATION_FIN_CALLBACK_URL'),
            'token' => env('INTEGRATION_FIN_TOKEN'),
        ],
    ],
];
