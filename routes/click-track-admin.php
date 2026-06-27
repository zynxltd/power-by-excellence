<?php

use App\Http\Controllers\Admin\ClickTrack\ClickController;
use App\Http\Controllers\Admin\ClickTrack\ConversionController;
use App\Http\Controllers\Admin\ClickTrack\DashboardController;
use App\Http\Controllers\Admin\ClickTrack\LinkController;
use App\Http\Controllers\Admin\ClickTrack\ReportController;
use App\Http\Controllers\Admin\ClickTrack\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('click-track')->name('click-track.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('links', [LinkController::class, 'index'])->name('links.index');
    Route::post('links', [LinkController::class, 'store'])->name('links.store');
    Route::patch('links/{trackingLink}', [LinkController::class, 'update'])->name('links.update');
    Route::delete('links/{trackingLink}', [LinkController::class, 'destroy'])->name('links.destroy');
    Route::get('clicks', [ClickController::class, 'index'])->name('clicks.index');
    Route::get('clicks/export', [ClickController::class, 'export'])->name('clicks.export');
    Route::get('conversions', [ConversionController::class, 'index'])->name('conversions.index');
    Route::post('conversions/bulk-approve', [ConversionController::class, 'bulkApprove'])->name('conversions.bulk-approve');
    Route::post('conversions/{trackingConversion}/approve', [ConversionController::class, 'approve'])->name('conversions.approve');
    Route::post('conversions/{trackingConversion}/reject', [ConversionController::class, 'reject'])->name('conversions.reject');
    Route::get('conversions/export', [ConversionController::class, 'export'])->name('conversions.export');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
});
