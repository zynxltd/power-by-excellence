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

    'default_ping_timeout_ms' => (int) env('CALL_PING_TIMEOUT_MS', 800),

    'default_min_duration_seconds' => (int) env('CALL_MIN_DURATION_SECONDS', 60),

    'recording_enabled' => env('CALL_RECORDING_ENABLED', false),

];
