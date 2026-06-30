<?php

use App\Http\Controllers\Admin\DistributionController;
use Illuminate\Support\Facades\Route;

/**
 * Leadbyte Phase 3 — F5 ping tree cap usage (route manifest).
 *
 * Wire inside the authenticated admin middleware group in routes/web.php:
 *
 *   require __DIR__.'/leadbyte-phase-3.php';
 *
 * F5 new route:
 *   GET  distribution/{distribution}/cap-usage               distribution.cap-usage
 *
 * Existing distribution resource routes remain in web.php — do not duplicate.
 */
Route::get('distribution/{distribution}/cap-usage', [DistributionController::class, 'capUsage'])
    ->name('distribution.cap-usage');

/**
 * Leadbyte Phase 3 — F6 delivery test harness.
 *
 * Existing route (already in web.php — do not duplicate):
 *   POST deliveries/{delivery}/test → deliveries.test
 *
 * Runs DeliveryTestHarnessService with mock buyer modes: accept | reject | timeout | custom.
 * Logs test runs in delivery_logs with _meta.is_test = true.
 */
