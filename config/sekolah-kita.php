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

    'queue' => [
        /*
         * The queues a supervised worker must consume. The runbook and the
         * Supervisor/systemd templates are generated from this list so the
         * worker definition and the application's dispatch targets cannot
         * drift apart.
         */
        'queues' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('QUEUE_WORKER_QUEUES', 'notifications,media,maintenance')),
        ))),

        /*
         * The --timeout value that supervised workers must be started with.
         * Readiness fails closed when this is not strictly lower than the
         * queue connection's retry_after value, because a worker that outlives
         * retry_after allows the same job to run twice in parallel.
         */
        'worker_timeout_seconds' => (int) env('QUEUE_WORKER_TIMEOUT', 150),
        'worker_tries' => (int) env('QUEUE_WORKER_TRIES', 3),
        'worker_sleep_seconds' => (int) env('QUEUE_WORKER_SLEEP', 1),

        /*
         * Retention for the durable failed_jobs table. Left unset, failed job
         * evidence is never pruned automatically.
         */
        'prune_failed_after_days' => env('QUEUE_PRUNE_FAILED_AFTER_DAYS'),

        /*
         * Queue depth threshold that the scheduled monitor reports on.
         */
        'monitor_threshold' => (int) env('QUEUE_MONITOR_THRESHOLD', 100),
    ],

    'security' => [
        /*
         * Administrator multi-factor authentication is not implemented in the
         * application. Readiness reports the requirement rather than silently
         * passing, and the flag lets a named owner record an accepted,
         * time-boxed exception without editing code.
         */
        'administrator_mfa_required' => (bool) env('ADMIN_MFA_REQUIRED', true),

        /*
         * Session inactivity window enforced for authenticated users, in
         * minutes, independent of the session cookie lifetime.
         */
        'session_idle_timeout_minutes' => (int) env('SESSION_IDLE_TIMEOUT', 30),
    ],
];
