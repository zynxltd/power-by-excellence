<?php

use App\Http\Controllers\Api\BuyerController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\Integrations\LeadSourceWebhookController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\MockBuyerApiController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\QuarantineController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware([LogApiRequest::class])->group(function () {
    Route::get('/mock/buyers', [MockBuyerApiController::class, 'docs']);
    Route::match(['get', 'post'], '/mock/buyers/{tier}/ping', [MockBuyerApiController::class, 'ping'])->whereNumber('tier');
    Route::match(['get', 'post'], '/mock/buyers/{tier}/post', [MockBuyerApiController::class, 'post'])->whereNumber('tier');

    Route::middleware([AuthenticateApiKey::class.':leads.create'])->group(function () {
        Route::post('/leads', [LeadController::class, 'store']);
        Route::post('/leads/import', [ImportController::class, 'store']);
    });

    Route::middleware([AuthenticateApiKey::class.':leads.read'])->group(function () {
        Route::get('/leads/{uuid}', [LeadController::class, 'show']);
        Route::get('/leads/queue/{queueId}', [LeadController::class, 'queueStatus']);
        Route::post('/leads/search', [LeadController::class, 'search']);
        Route::post('/leads/{uuid}/reprocess', [LeadController::class, 'reprocess']);
    });

    Route::middleware([AuthenticateApiKey::class.':reports.read'])->group(function () {
        Route::get('/reports/leads', [ReportController::class, 'leads']);
        Route::get('/reports/revenue', [ReportController::class, 'revenue']);
    });

    Route::middleware([AuthenticateApiKey::class.':platform.read'])->group(function () {
        Route::get('/platform', [PlatformController::class, 'show']);
        Route::get('/platform/campaigns/{reference}', [PlatformController::class, 'campaign']);
    });

    Route::middleware([AuthenticateApiKey::class.':quarantine.manage'])->group(function () {
        Route::get('/quarantine', [QuarantineController::class, 'index']);
        Route::post('/quarantine/{uuid}/release', [QuarantineController::class, 'release']);
        Route::post('/quarantine/{uuid}/reject', [QuarantineController::class, 'reject']);
    });

    Route::middleware([AuthenticateApiKey::class.':buyers.manage'])->group(function () {
        Route::post('/buyers/{buyer}/feedback', [BuyerController::class, 'feedback']);
        Route::post('/buyers/{buyer}/credit', [BuyerController::class, 'addCredit']);
    });

    Route::post('/ping', function (Request $request) {
        $floor = (float) $request->input('floor', 10);
        $hint = (float) $request->input('bid_hint', 0);
        $cost = $hint > 0 ? $hint : max($floor, 15 + random_int(0, 5));

        return response()->json([
            'Success' => true,
            'Cost' => $cost,
            'PingID' => 'ping_'.uniqid(),
        ]);
    });

    Route::post('/post', function () {
        return response()->json(['Success' => true, 'Approved' => true]);
    });

    Route::match(['get', 'post'], '/integrations/{provider}/webhook/{accountSlug}', [LeadSourceWebhookController::class, 'verify'])
        ->whereIn('provider', ['facebook', 'google', 'tiktok']);
    Route::post('/integrations/{provider}/ingest/{accountSlug}', [LeadSourceWebhookController::class, 'ingest'])
        ->whereIn('provider', ['facebook', 'google', 'tiktok']);
});
