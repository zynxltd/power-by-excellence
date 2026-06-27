<?php

/**
 * Call Logic buyer portal routes — wire inside portal.buyer group in routes/web.php:
 *
 *   require __DIR__.'/call-logic-portal.php';
 */

use App\Http\Controllers\Portal\BuyerCallPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/calls', [BuyerCallPortalController::class, 'index'])->name('portal.buyer.calls');
Route::get('/calls/{call:uuid}', [BuyerCallPortalController::class, 'show'])->name('portal.buyer.calls.show');
Route::get('/calls-export', [BuyerCallPortalController::class, 'export'])->name('portal.buyer.calls.export');
