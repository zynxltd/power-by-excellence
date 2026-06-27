<?php

use App\ClickTrack\ClickTrackBootstrap;
use App\Http\Controllers\ClickRedirectController;
use App\Http\Controllers\ImpressionPixelController;
use Illuminate\Support\Facades\Route;

ClickTrackBootstrap::registerListeners();

Route::get('/c/{token}', ClickRedirectController::class)->name('click.redirect');
Route::get('/i/{token}', ImpressionPixelController::class)->name('click.impression');
