<?php

use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\DistributionController;
use App\Http\Controllers\Admin\VerticalFieldTemplateController;
use Illuminate\Support\Facades\Route;

/**
 * Leadbyte Phase 3 route manifest.
 *
 * Wire inside the authenticated admin middleware group in routes/web.php:
 *
 *   require __DIR__.'/leadbyte-phase-3.php';
 *
 * Existing routes (already in web.php — do not duplicate):
 *   GET  vertical-field-templates                              vertical-field-templates.index
 *   POST vertical-field-templates                              vertical-field-templates.store
 *   POST vertical-field-templates/{verticalFieldTemplate}/apply vertical-field-templates.apply
 *
 * F4 — vertical field template apply wizard:
 *   GET  vertical-field-templates/apply-wizard                 vertical-field-templates.apply-wizard
 *   POST vertical-field-templates/{verticalFieldTemplate}/preview vertical-field-templates.preview
 *
 * F5 — ping tree live cap usage:
 *   GET  distribution/{distribution}/cap-usage               distribution.cap-usage
 */
Route::get('vertical-field-templates/apply-wizard', [VerticalFieldTemplateController::class, 'applyWizard'])
    ->name('vertical-field-templates.apply-wizard');
Route::post('vertical-field-templates/{verticalFieldTemplate}/preview', [VerticalFieldTemplateController::class, 'preview'])
    ->name('vertical-field-templates.preview');

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

/**
 * Leadbyte Phase 3 F7 campaign clone.
 *
 *   POST campaigns/{campaign}/clone → campaigns.clone
 */
Route::post('campaigns/{campaign}/clone', [CampaignController::class, 'clone'])
    ->name('campaigns.clone');
