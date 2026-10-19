<?php

return [
    'base_url' => env('VITALPBX_API_BASE_URL'),
    'api_key' => env('VITALPBX_API_KEY'),
    'tenant' => env('VITALPBX_TENANT'),
    'timeout' => max(1, (int) env('VITALPBX_TIMEOUT', 10)),
];
