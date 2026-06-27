<?php

use App\Http\Controllers\Portal\SupplierPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/clicks', [SupplierPortalController::class, 'clicks'])->name('clicks');
