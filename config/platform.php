<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Queue resilience
    |--------------------------------------------------------------------------
    |
    | Prefer Redis + Horizon in production. When Redis is unreachable at boot,
    | the app falls back to the database driver and the scheduler drains the
    | queue each minute (see bootstrap/app.php).
    |
    */

    'queue' => [
        'preferred_connection' => env('QUEUE_CONNECTION', 'database'),
        'fallback_connection' => env('QUEUE_FALLBACK_CONNECTION', 'database'),
        'redis_fallback' => env('QUEUE_REDIS_FALLBACK', true),
        'fallback_active' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Super-admin alert thresholds
    |--------------------------------------------------------------------------
    |
    | Synced into platform_notifications (type=system) for the super-admin bell
    | and command center. Tune per environment via .env if needed.
    |
    */

    'admin_alerts' => [
        'failed_jobs_warning' => (int) env('ADMIN_ALERT_FAILED_JOBS_WARN', 1),
        'failed_jobs_critical' => (int) env('ADMIN_ALERT_FAILED_JOBS_CRITICAL', 10),
        'queue_backlog_warning' => (int) env('ADMIN_ALERT_QUEUE_BACKLOG_WARN', 100),
        'queue_backlog_critical' => (int) env('ADMIN_ALERT_QUEUE_BACKLOG_CRITICAL', 500),
        'production_errors_window_minutes' => (int) env('ADMIN_ALERT_ERRORS_WINDOW', 15),
        'production_errors_threshold' => (int) env('ADMIN_ALERT_ERRORS_THRESHOLD', 3),
        'exception_alert_cooldown_seconds' => (int) env('ADMIN_ALERT_EXCEPTION_COOLDOWN', 300),
    ],

    'security' => [
        'admin_ip_allowlist_bypass' => (bool) env(
            'ADMIN_IP_ALLOWLIST_BYPASS',
            env('APP_ENV') === 'local',
        ),
    ],

];
