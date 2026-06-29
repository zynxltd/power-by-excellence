<?php

/**
 * Compliance Phase 3 — data retention (F1).
 *
 * HTTP routes: none new. Retention policy is saved via existing platform settings:
 *
 *   GET  settings.edit   → AccountSettingsController@edit
 *   PUT  settings.update → AccountSettingsController@update
 *
 * Integration Lead — schedule in bootstrap/app.php (inside withSchedule):
 *
 *   $schedule->command('data-retention:purge')->dailyAt('02:30')->withoutOverlapping();
 *
 * Manual purge for a single tenant:
 *
 *   php artisan data-retention:purge --account={id}
 */
