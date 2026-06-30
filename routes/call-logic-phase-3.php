<?php

use App\Http\Controllers\Admin\CallSessionController;
use App\Http\Controllers\Portal\BuyerCallPortalController;
use Illuminate\Support\Facades\Route;

/**
 * Call Logic Phase 3 route manifest.
 *
 * Integration Lead: call registerCallLogicPhase3ReturnRoutes() from
 * App\Support\CallLogic\CallLogicRouteRegistrar (portal + admin).
 *
 * F5 routes:
 *   POST portal/buyer/calls/{call}/return              portal.buyer.calls.return
 *   POST call-logic/calls/{call}/returns/{return}/approve  call-logic.calls.returns.approve
 *   POST call-logic/calls/{call}/returns/{return}/reject   call-logic.calls.returns.reject
 *
 * Setting: settings.call_logic.call_return_window_days (default 7)
 *
 * F6 (CT7) conversion postback URL builder — no new routes.
 * Uses existing PATCH click-track.links.update (routes/click-track-admin.php).
 * Adds tracking_links.conversion_postback_url + conversion_postback_macros.
 */

if (! function_exists('registerCallLogicPhase3PortalReturnRoutes')) {
    function registerCallLogicPhase3PortalReturnRoutes(): void
    {
        if (Route::has('portal.buyer.calls.return')) {
            return;
        }

        Route::post('calls/{call:uuid}/return', [BuyerCallPortalController::class, 'submitReturn'])
            ->name('calls.return');
    }
}

if (! function_exists('registerCallLogicPhase3AdminReturnRoutes')) {
    function registerCallLogicPhase3AdminReturnRoutes(): void
    {
        if (Route::has('call-logic.calls.returns.approve')) {
            return;
        }

        Route::post('calls/{call}/returns/{return}/approve', [CallSessionController::class, 'approveReturn'])
            ->name('calls.returns.approve');
        Route::post('calls/{call}/returns/{return}/reject', [CallSessionController::class, 'rejectReturn'])
            ->name('calls.returns.reject');
    }
}
