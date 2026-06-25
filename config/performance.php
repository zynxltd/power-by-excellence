<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lead pipeline performance targets
    |--------------------------------------------------------------------------
    */

    'target_processing_ms' => (int) env('PERFORMANCE_TARGET_MS', 200),

    'ping_timeout_seconds' => (int) env('PERFORMANCE_PING_TIMEOUT', 2),

    'post_timeout_seconds' => (int) env('PERFORMANCE_POST_TIMEOUT', 3),

    'delivery_timeout_seconds' => (int) env('PERFORMANCE_DELIVERY_TIMEOUT', 5),

];
