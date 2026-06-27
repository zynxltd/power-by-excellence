<?php

use App\Http\Controllers\ClickTrack\SupplierClickPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/clicks', SupplierClickPortalController::class)->name('clicks');
