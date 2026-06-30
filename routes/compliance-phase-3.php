<?php

/**
 * Compliance Phase 3 route manifests.
 *
 * F1 — Data retention: no new HTTP routes (settings.edit / settings.update).
 * Schedule: $schedule->command('data-retention:purge')->dailyAt('02:30')->withoutOverlapping();
 *
 * F2 — Audit log CSV export:
 *   registerCompliancePhase3LogExportRoutes();
 *
 * F3 — Outbound webhook HMAC signing:
 *   registerCompliancePhase3WebhookSigningRoutes();
 */

use App\Http\Controllers\Admin\AccessLogController;
use App\Http\Controllers\Admin\ChangeLogController;
use App\Http\Controllers\Admin\SecurityLogController;
use App\Http\Controllers\Admin\WebhookController;
use Illuminate\Support\Facades\Route;

if (! function_exists('registerCompliancePhase3LogExportRoutes')) {
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
}

if (! function_exists('registerCompliancePhase3WebhookSigningRoutes')) {
    function registerCompliancePhase3WebhookSigningRoutes(): void
    {
        if (! Route::has('webhooks.generate-signing-secret')) {
            Route::post('webhooks/generate-signing-secret', [WebhookController::class, 'generateSigningSecret'])
                ->name('webhooks.generate-signing-secret');
        }
    }
}
