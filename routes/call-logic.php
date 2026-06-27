<?php

/**
 * Call Logic — route definitions for Integration Lead to wire.
 *
 * Wire in routes/web.php (do not duplicate if already inline):
 *
 *   // Public (no auth) — top of web.php after other public routes:
 *   require __DIR__.'/call-logic.php';
 *
 * Admin routes in this file are wrapped in callLogicAdminRoutes() — require INSIDE
 * the authenticated admin middleware group:
 *
 *   callLogicAdminRoutes();
 *
 * Buyer portal routes — require INSIDE portal.buyer group:
 *
 *   require __DIR__.'/call-logic-portal.php';
 */

use App\Http\Controllers\Admin\CallCampaignSettingsController;
use App\Http\Controllers\Admin\CallLogicReportController;
use App\Http\Controllers\Admin\CallLogicSettingsController;
use App\Http\Controllers\Admin\CallSessionController;
use App\Http\Controllers\Admin\IvrFlowController;
use App\Http\Controllers\Admin\TrackingNumberController;
use App\Http\Controllers\Webhooks\TwilioVoiceWebhookController;
use Illuminate\Support\Facades\Route;

// --- Public telephony webhooks + DNI SDK (no auth) ---
Route::prefix('webhooks/twilio/voice/{accountSlug}')->group(function () {
    Route::post('/', [TwilioVoiceWebhookController::class, 'inbound']);
    Route::post('/gather', [TwilioVoiceWebhookController::class, 'gather']);
    Route::post('/status', [TwilioVoiceWebhookController::class, 'status']);
    Route::post('/recording', [TwilioVoiceWebhookController::class, 'recording']);
});

Route::get('/sdk/pbe-calls.js', function () {
    return response()->file(base_path('sdk/javascript/pbe-calls.js'), [
        'Content-Type' => 'application/javascript',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('sdk.calls');

if (! function_exists('callLogicAdminRoutes')) {
    function callLogicAdminRoutes(): void
    {
        Route::get('call-logic/settings', [CallLogicSettingsController::class, 'edit'])
            ->name('call-logic.settings.edit');
        Route::put('call-logic/settings', [CallLogicSettingsController::class, 'update'])
            ->name('call-logic.settings.update');

        Route::prefix('call-logic')
            ->name('call-logic.')
            ->middleware('product.enabled:call_logic')
            ->group(function () {
                Route::get('calls', [CallSessionController::class, 'index'])->name('calls.index');
                Route::get('calls/export', [CallSessionController::class, 'export'])->name('calls.export');
                Route::get('calls/{call}', [CallSessionController::class, 'show'])->name('calls.show');
                Route::get('tracking-numbers', [TrackingNumberController::class, 'index'])->name('tracking-numbers.index');
                Route::post('tracking-numbers', [TrackingNumberController::class, 'store'])->name('tracking-numbers.store');
                Route::delete('tracking-numbers/{trackingNumber}', [TrackingNumberController::class, 'destroy'])->name('tracking-numbers.destroy');
                Route::get('ivr', [IvrFlowController::class, 'index'])->name('ivr.index');
                Route::get('ivr/create', [IvrFlowController::class, 'create'])->name('ivr.create');
                Route::post('ivr', [IvrFlowController::class, 'store'])->name('ivr.store');
                Route::get('ivr/{ivrFlow}/edit', [IvrFlowController::class, 'edit'])->name('ivr.edit');
                Route::put('ivr/{ivrFlow}', [IvrFlowController::class, 'update'])->name('ivr.update');
                Route::delete('ivr/{ivrFlow}', [IvrFlowController::class, 'destroy'])->name('ivr.destroy');
                Route::get('reports', [CallLogicReportController::class, 'index'])->name('reports.index');
                Route::patch('campaigns/{campaign}/call-settings', [CallCampaignSettingsController::class, 'update'])
                    ->name('campaigns.call-settings.update');
            });
    }
}
