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

    /*
    |--------------------------------------------------------------------------
    | Platform quality gates (Command Center / status page)
    |--------------------------------------------------------------------------
    */

    'post_success_rate_target' => (float) env('PERFORMANCE_POST_SUCCESS_TARGET', 95),

    /** Minimum posts today before post-success warnings apply. */
    'post_success_rate_min_posts' => (int) env('PERFORMANCE_POST_SUCCESS_MIN_POSTS', 5),

    /** P95 processing may exceed target by this factor before warning (e.g. 1.5 × 200ms). */
    'p95_warning_factor' => (float) env('PERFORMANCE_P95_WARNING_FACTOR', 1.5),

    /** Scheduler / status snapshot must refresh within this many minutes. */
    'scheduler_stale_minutes' => (int) env('PERFORMANCE_SCHEDULER_STALE_MINUTES', 20),

];
