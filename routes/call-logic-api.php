<?php

/**
 * Call Logic API routes — wire in routes/api.php inside the v1 prefix group:
 *
 *   require __DIR__.'/call-logic-api.php';
 */

use App\Http\Controllers\Api\CallDispositionController;
use App\Http\Controllers\Api\CallDniController;
use App\Http\Controllers\Api\CallReportController;
use App\Http\Controllers\Api\MockCallBuyerApiController;
use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware([AuthenticateApiKey::class.':reports.read'])->group(function () {
    Route::get('/reports/calls', [CallReportController::class, 'index']);
});

Route::middleware([AuthenticateApiKey::class.':leads.read'])->group(function () {
    Route::get('/calls/{uuid}', [CallDispositionController::class, 'show']);
    Route::post('/calls/{uuid}/disposition', [CallDispositionController::class, 'store']);
});

Route::get('/dni/resolve', [CallDniController::class, 'resolve']);

Route::match(['get', 'post'], '/mock/call-buyers/{tier}/ping', [MockCallBuyerApiController::class, 'ping'])
    ->whereNumber('tier');
