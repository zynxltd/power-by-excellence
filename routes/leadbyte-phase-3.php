<?php

use App\Http\Controllers\Admin\VerticalFieldTemplateController;
use Illuminate\Support\Facades\Route;

/**
 * Leadbyte Phase 3 — F4 vertical field template apply wizard.
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
 * New routes (this manifest):
 *   GET  vertical-field-templates/apply-wizard                 vertical-field-templates.apply-wizard
 *   POST vertical-field-templates/{verticalFieldTemplate}/preview vertical-field-templates.preview
 */
Route::get('vertical-field-templates/apply-wizard', [VerticalFieldTemplateController::class, 'applyWizard'])
    ->name('vertical-field-templates.apply-wizard');
Route::post('vertical-field-templates/{verticalFieldTemplate}/preview', [VerticalFieldTemplateController::class, 'preview'])
    ->name('vertical-field-templates.preview');
