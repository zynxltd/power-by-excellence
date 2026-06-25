<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            \App\Http\Middleware\SetAccountFromHost::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();

            if ($user && \App\Support\Tenancy\TenantResolver::isCentralHost($request->getHost()) && ! $user->isSuperAdmin()) {
                $account = $user->resolveAccount();

                if ($account) {
                    return \App\Support\Tenancy\TenantResolver::portalUrl($account, '/dashboard');
                }
            }

            return route('dashboard', absolute: false);
        });

        $middleware->alias([
            'api.key' => \App\Http\Middleware\AuthenticateApiKey::class,
            'account' => \App\Http\Middleware\SetAccountFromUser::class,
            'portal' => \App\Http\Middleware\EnsurePortalRole::class,
            'billing.active' => \App\Http\Middleware\EnsureAccountBillingActive::class,
            'marketing.central' => \App\Http\Middleware\RestrictMarketingToCentralHost::class,
            'superadmin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'central.host' => \App\Http\Middleware\EnsureCentralHost::class,
            'module.access' => \App\Http\Middleware\EnsureModuleAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->report(function (\Throwable $e) {
            if (app()->runningInConsole()) {
                return;
            }
            try {
                \App\Services\Logging\PlatformLogger::error(
                    $e->getMessage(),
                    ['exception_class' => $e::class],
                    null,
                    $e
                );
            } catch (Throwable) {
                // fail silently
            }
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('quarantine:process-expired')->everyFifteenMinutes();
    })
    ->create();
