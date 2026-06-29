<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default telephony provider
    |--------------------------------------------------------------------------
    |
    | log: simulate calls in logs (development)
    | twilio: Twilio Voice API
    |
    */

    'provider' => env('TELEPHONY_PROVIDER', 'log'),

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'webhook_base' => env('TWILIO_WEBHOOK_BASE'),
    ],

    'default_country' => env('TELEPHONY_DEFAULT_COUNTRY', 'GB'),

    'search_limit' => (int) env('TELEPHONY_SEARCH_LIMIT', 10),

    'webhook_paths' => [
        'voice' => '/webhooks/twilio/voice/{accountSlug}',
        'gather' => '/webhooks/twilio/voice/{accountSlug}/gather',
        'status' => '/webhooks/twilio/voice/{accountSlug}/status',
        'recording' => '/webhooks/twilio/voice/{accountSlug}/recording',
    ],

    'default_ping_timeout_ms' => (int) env('CALL_PING_TIMEOUT_MS', 800),

    'default_min_duration_seconds' => (int) env('CALL_MIN_DURATION_SECONDS', 60),

    'recording_enabled' => env('CALL_RECORDING_ENABLED', false),

    'recording_disk' => env('CALL_RECORDING_DISK', 'local'),

    'recording_retention_days' => (int) env('CALL_RECORDING_RETENTION_DAYS', 90),

];
