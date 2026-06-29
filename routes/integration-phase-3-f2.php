<?php

use Illuminate\Support\Facades\Route;

/**
 * Integration Phase 3 — F2 Supplier import polish.
 *
 * Wire in routes/web.php inside portal.supplier middleware group:
 *
 *   require __DIR__.'/integration-phase-3-f2.php';
 *
 * Existing routes (verify registered):
 *   GET  portal/supplier/leads/import              portal.supplier.leads.import
 *   POST portal/supplier/leads/import              portal.supplier.leads.import.store
 *
 * New route:
 *   GET portal/supplier/leads/import/{import}/errors portal.supplier.leads.import.errors
 */
Route::middleware(['auth', 'verified'])->prefix('portal/supplier')->name('portal.supplier.')->group(function () {
    Route::get('/leads/import/{import}/errors', [\App\Http\Controllers\Portal\SupplierPortalController::class, 'downloadImportErrors'])
        ->name('leads.import.errors');
});
