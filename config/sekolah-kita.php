<?php

return [
    'audit' => [
        'failure_mode' => env('AUDIT_FAILURE_MODE', 'throw'),
        'retention_days' => env('AUDIT_RETENTION_DAYS'),
    ],

    'notifications' => [
        'queue' => env('NOTIFICATION_QUEUE', 'notifications'),
        'email_enabled' => (bool) env('NOTIFICATION_EMAIL_ENABLED', true),
    ],

    'ppdb' => [
        'consent_version' => env('PPDB_CONSENT_VERSION', '2026-01'),
        'access_token_ttl_days' => (int) env('PPDB_ACCESS_TOKEN_TTL_DAYS', 7),
        'max_document_bytes' => (int) env('PPDB_MAX_DOCUMENT_BYTES', 10_485_760),
    ],

    'platform' => [
        'redis_required_in_production' => (bool) env('REDIS_REQUIRED_IN_PRODUCTION', true),
        'health_check_timeout_seconds' => (int) env('HEALTH_CHECK_TIMEOUT_SECONDS', 2),
    ],
];
