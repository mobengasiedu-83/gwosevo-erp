<?php

return [
    'base_url' => env('VITALPBX_API_BASE_URL'),
    'api_key' => env('VITALPBX_API_KEY'),
    'tenant' => env('VITALPBX_TENANT'),
    'timeout' => max(1, (int) env('VITALPBX_TIMEOUT', 10)),
    'cdr_initial_lookback_days' => max(1, (int) env('VITALPBX_CDR_INITIAL_LOOKBACK_DAYS', 7)),
    'cdr_overlap_hours' => max(1, (int) env('VITALPBX_CDR_OVERLAP_HOURS', 24)),
    'cdr_page_size' => max(1, (int) env('VITALPBX_CDR_PAGE_SIZE', 250)),
];
