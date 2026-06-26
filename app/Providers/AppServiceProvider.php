<?php

namespace App\Providers;

use App\Support\Platform\ResilientQueueBootstrap;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResilientQueueBootstrap::apply();

        Vite::prefetch(concurrency: 3);

        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function (object $user, string $token) {
            $account = $user instanceof \App\Models\User ? $user->resolveAccount() : null;
            $path = route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], false);

            if ($account) {
                return \App\Support\Tenancy\TenantResolver::portalUrl($account, $path);
            }

            return url($path);
        });
    }
}
