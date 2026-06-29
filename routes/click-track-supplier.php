<?php

use App\Http\Controllers\ClickTrack\SupplierClickPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/clicks', [SupplierClickPortalController::class, '__invoke'])->name('clicks');
Route::get('/clicks/export', [SupplierClickPortalController::class, 'export'])->name('clicks.export');
