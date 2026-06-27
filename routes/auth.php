<?php

use App\Http\Controllers\Auth\AddressVerificationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\PhoneVerificationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Middleware\EnsureCentralLoginOnly;
use App\Http\Middleware\EnsureTenantAccess;

Route::get('impersonate/handoff/{token}', [\App\Http\Controllers\Admin\ImpersonationController::class, 'handoff'])
    ->name('impersonate.handoff');
Route::get('impersonate/stop-handoff/{token}', [\App\Http\Controllers\Admin\ImpersonationController::class, 'stopHandoff'])
    ->name('impersonate.stop-handoff');
Route::get('god-mode/stop-handoff/{token}', [\App\Http\Controllers\Admin\ImpersonationController::class, 'godModeStopHandoff'])
    ->name('god-mode.stop-handoff');

// Public registration disabled - users are created by admins only.
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth', EnsureTenantAccess::class])->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])->middleware('throttle:6,1')->name('verification.send');

    Route::get('verify-phone', [PhoneVerificationController::class, 'show'])->name('verification.phone');
    Route::post('verify-phone/send', [PhoneVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.phone.send');
    Route::post('verify-phone', [PhoneVerificationController::class, 'verify'])->middleware('throttle:12,1')->name('verification.phone.verify');

    Route::get('verify-address', [AddressVerificationController::class, 'show'])->name('verification.address');
    Route::post('verify-address', [AddressVerificationController::class, 'store'])->name('verification.address.store');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
