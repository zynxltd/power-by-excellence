<?php

/**
 * Compliance Phase 3 route manifests.
 *
 * F1 — Data retention: no new HTTP routes (settings.edit / settings.update).
 * Schedule: $schedule->command('data-retention:purge')->dailyAt('02:30')->withoutOverlapping();
 *
 * F3 — Outbound webhook HMAC signing (register inside admin middleware group):
 *   POST webhooks/generate-signing-secret → webhooks.generate-signing-secret
 */

use App\Http\Controllers\Admin\WebhookController;
use Illuminate\Support\Facades\Route;

if (! function_exists('registerCompliancePhase3WebhookSigningRoutes')) {
    function registerCompliancePhase3WebhookSigningRoutes(): void
    {
        if (! Route::has('webhooks.generate-signing-secret')) {
            Route::post('webhooks/generate-signing-secret', [WebhookController::class, 'generateSigningSecret'])
                ->name('webhooks.generate-signing-secret');
        }
    }
}
