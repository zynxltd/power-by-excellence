<?php

/**
 * Compliance Phase 3 — platform compliance features.
 *
 * F1 — Data retention (settings only; no new HTTP routes):
 *   GET  settings.edit   → AccountSettingsController@edit
 *   PUT  settings.update → AccountSettingsController@update
 *
 * Integration Lead — schedule in bootstrap/app.php (inside withSchedule):
 *   $schedule->command('data-retention:purge')->dailyAt('02:30')->withoutOverlapping();
 *
 * F2 — Audit log CSV export (register inside admin middleware group):
 *   registerCompliancePhase3LogExportRoutes();
 */

use App\Http\Controllers\Admin\AccessLogController;
use App\Http\Controllers\Admin\ChangeLogController;
use App\Http\Controllers\Admin\SecurityLogController;
use Illuminate\Support\Facades\Route;

function registerCompliancePhase3LogExportRoutes(): void
{
    if (! Route::has('logs.access.export')) {
        Route::get('logs/access/export', [AccessLogController::class, 'export'])->name('logs.access.export');
    }

    if (! Route::has('logs.changes.export')) {
        Route::get('logs/changes/export', [ChangeLogController::class, 'export'])->name('logs.changes.export');
    }

    if (! Route::has('logs.security.export')) {
        Route::get('logs/security/export', [SecurityLogController::class, 'export'])->name('logs.security.export');
    }
}
