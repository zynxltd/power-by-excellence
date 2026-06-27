<?php

/**
 * Click Track API routes — wire in routes/api.php inside the v1 prefix group:
 *   require __DIR__.'/click-track-api.php';
 */

use App\Http\Controllers\Api\ClickTrackConversionController;
use App\Http\Controllers\Api\ClickTrackReportController;
use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware([AuthenticateApiKey::class.':clicks.read'])->group(function () {
    Route::get('/click-track/summary', [ClickTrackReportController::class, 'summary']);
    Route::get('/click-track/performance', [ClickTrackReportController::class, 'performance']);
});

Route::middleware([AuthenticateApiKey::class.':conversions.manage'])->group(function () {
    Route::post('/conversions', [ClickTrackConversionController::class, 'store']);
});
