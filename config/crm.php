<?php

return [
    'fit_weight' => (int) env('CRM_FIT_WEIGHT', 60),
    'engagement_weight' => (int) env('CRM_ENGAGEMENT_WEIGHT', 40),
    'follow_up_escalation_hours' => (int) env('CRM_FOLLOW_UP_ESCALATION_HOURS', 24),
    'website_capture_secret' => env('CRM_WEBSITE_CAPTURE_SECRET'),
    'sources' => [
        'manual' => 'Manual', 'website' => 'Website', 'api' => 'API',
        'import' => 'Approved import', 'referral' => 'Referral', 'social' => 'Social media',
        'inbound_call' => 'Inbound call', 'event' => 'Event', 'campaign' => 'Campaign',
    ],
];
