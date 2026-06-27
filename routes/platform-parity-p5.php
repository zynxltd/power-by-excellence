<?php

/**
 * P5 — User & webhook edit parity routes (already wired in routes/web.php).
 *
 * Integration Lead: verify these names remain registered; no nav changes required.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('users/{user}/edit', [\App\Http\Controllers\Admin\UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/suspend', [\App\Http\Controllers\Admin\UserController::class, 'suspend'])->name('users.suspend');
    Route::post('users/{user}/activate', [\App\Http\Controllers\Admin\UserController::class, 'activate'])->name('users.activate');
    Route::post('users/{user}/email-credentials', [\App\Http\Controllers\Admin\UserController::class, 'emailCredentials'])->name('users.email-credentials');

    Route::put('webhooks/{webhook}', [\App\Http\Controllers\Admin\WebhookController::class, 'update'])->name('webhooks.update');
});
