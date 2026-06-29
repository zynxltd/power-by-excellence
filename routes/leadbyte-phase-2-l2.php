<?php

/**
 * L2 — Portal custom domain verification (Leadbyte Phase 2).
 *
 * Integration Lead: wire inside the authenticated admin middleware group in routes/web.php:
 *
 *   require __DIR__.'/leadbyte-phase-2-l2.php';
 *
 * TenantResolver::portalHost and resolveFromHost are updated in-app to use PortalDomain.
 * No AdminTopNav / TenantHub changes required — portal domain lives on existing Settings page.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('settings/portal-domain/verify', [\App\Http\Controllers\Admin\AccountSettingsController::class, 'verifyPortalDomain'])
        ->name('settings.portal-domain.verify');
});
