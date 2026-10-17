<?php

return [
    'mfa_required' => env('IDENTITY_MFA_REQUIRED', true),
    'entra' => [
        'enabled' => env('ENTRA_ENABLED', false),
        'tenant_id' => env('ENTRA_TENANT_ID'),
        'client_id' => env('ENTRA_CLIENT_ID'),
        'client_secret' => env('ENTRA_CLIENT_SECRET'),
        'redirect_uri' => env('ENTRA_REDIRECT_URI'),
    ],
    'review_interval_days' => (int) env('IDENTITY_REVIEW_INTERVAL_DAYS', 90),
];
