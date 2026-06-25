<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live stats polling interval (seconds)
    |--------------------------------------------------------------------------
    |
    | How often the admin UI refreshes operational counters on each page.
    |
    */

    'live_stats_interval' => (int) env('LIVE_STATS_INTERVAL', 15),

    'max_repost_attempts' => (int) env('MAX_REPOST_ATTEMPTS', 3),

];
