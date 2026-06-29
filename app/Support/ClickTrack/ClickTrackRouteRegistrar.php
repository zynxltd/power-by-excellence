<?php

namespace App\Support\ClickTrack;

use App\ClickTrack\ClickTrackBootstrap;
use App\Http\Middleware\EnsurePortalRole;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\SetAccountFromUser;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

final class ClickTrackRouteRegistrar
{
    private static bool $apiRoutesLoaded = false;

    public static function register(): void
    {
        /** @var Router $router */
        $router = app('router');

        if (! $router->getRoutes()->getByName('click.redirect')) {
            require base_path('routes/click-track-public.php');
            ClickTrackBootstrap::registerListeners();
        } else {
            ClickTrackBootstrap::registerListeners();
        }

        if (! $router->getRoutes()->getByName('click-track.dashboard')) {
            Route::middleware([
                'web', 'auth', 'verified', 'signup.complete',
                SetAccountFromUser::class, EnsureTenantAccess::class,
                'billing.active', EnsurePortalRole::class.':admin', 'module.access',
            ])->group(function (): void {
                require base_path('routes/click-track-admin.php');
            });
        }

        if (! $router->getRoutes()->getByName('portal.supplier.clicks.export')) {
            Route::middleware([
                'web', 'auth', 'verified', 'signup.complete',
                SetAccountFromUser::class, EnsureTenantAccess::class,
                'billing.active', EnsurePortalRole::class.':supplier',
            ])->prefix('portal/supplier')->name('portal.supplier.')->group(function (): void {
                require base_path('routes/click-track-supplier.php');
            });
        }

        if (! self::$apiRoutesLoaded) {
            Route::prefix('api/v1')->middleware([LogApiRequest::class])->group(function (): void {
                require base_path('routes/click-track-api.php');
            });
            self::$apiRoutesLoaded = true;
        }

        $router->getRoutes()->refreshNameLookups();
    }
}
