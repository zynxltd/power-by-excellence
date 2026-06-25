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

];
