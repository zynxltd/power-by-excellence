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
        $middleware->trustProxies(at: '*');

        $middleware->web(prepend: [
            \App\Http\Middleware\SetAccountFromHost::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\BridgeSessionFlashToInertia::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();

            if ($user?->isBuyerPortal()) {
                return route('portal.buyer.dashboard', absolute: false);
            }

            if ($user?->isSupplierPortal()) {
                return route('portal.supplier.dashboard', absolute: false);
            }

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
            'hosted-form.embed' => \App\Http\Middleware\HostedFormEmbedHeaders::class,
            'signup.complete' => \App\Http\Middleware\EnsureSignupComplete::class,
            'two-factor.verified' => \App\Http\Middleware\EnsureTwoFactorVerified::class,
            'product.enabled' => \App\Http\Middleware\EnsureProductEnabled::class,
            'admin.ip-allowlist' => \App\Http\Middleware\EnsureAdminIpAllowlist::class,
        ]);

        $middleware->priority([
            \App\Http\Middleware\AuthenticateApiKey::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 403 || $request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            $user = $request->user();
            if (! $user || $user->isSuperAdmin()) {
                return null;
            }

            $account = $user->resolveAccount();
            if (! $account) {
                return null;
            }

            $hostAccount = $request->attributes->get('host_account')
                ?? \App\Support\Tenancy\TenantResolver::resolveFromHost($request->getHost());

            if (! $hostAccount || $user->belongsToAccount($hostAccount)) {
                return null;
            }

            $message = $e->getMessage() ?: 'Access denied.';
            $path = $request->getPathInfo() ?: '/dashboard';
            $url = \App\Support\Tenancy\TenantResolver::portalUrl($account, $path);

            if ($request->hasSession()) {
                $request->session()->flash('error', $message);
            }

            if ($request->header('X-Inertia')) {
                return \Inertia\Inertia::location($url);
            }

            return redirect()->away($url);
        });

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

                app(\App\Services\Platform\PlatformAdminAlertService::class)->notifyUncaughtException($e);
            } catch (Throwable) {
                // fail silently
            }
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('quarantine:process-expired')->everyFifteenMinutes();
        $schedule->command('platform:status-snapshot')->everyFifteenMinutes();
        $schedule->command('platform:status-snapshot --persist')->dailyAt('06:00');
        $schedule->command('platform:sync-alerts')->everyFiveMinutes();
        $schedule->command('exports:process')->everyMinute()->withoutOverlapping();
        $schedule->command('bulk:process-scheduled')->everyMinute()->withoutOverlapping();
        $schedule->command('automation:process-sequences')->everyMinute()->withoutOverlapping();
        $schedule->command('reports:process-scheduled')->everyMinute()->withoutOverlapping();
        $schedule->command('messaging:process-scheduled')->everyMinute()->withoutOverlapping();
        $schedule->command('data-retention:purge')->dailyAt('02:30')->withoutOverlapping();

        if (class_exists(\Laravel\Horizon\Horizon::class)) {
            $schedule->command('horizon:snapshot')->everyFiveMinutes();
        }

        $schedule->command('queue:work database --stop-when-empty --max-time=55 --tries=3')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground()
            ->name('process-database-queue')
            ->when(fn () => config('queue.default') === 'database');
    })
    ->create();
