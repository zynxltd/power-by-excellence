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
    public static function register(): void
    {
        /** @var Router $router */
        $router = app('router');

        if ($router->getRoutes()->getByName('click-track.dashboard')) {
            return;
        }

        require base_path('routes/click-track-public.php');

        ClickTrackBootstrap::registerListeners();

        Route::middleware([
            'web', 'auth', 'verified', 'signup.complete',
            SetAccountFromUser::class, EnsureTenantAccess::class,
            'billing.active', EnsurePortalRole::class.':admin', 'module.access',
        ])->group(function (): void {
            require base_path('routes/click-track-admin.php');
        });

        Route::middleware([
            'web', 'auth', 'verified', 'signup.complete',
            SetAccountFromUser::class, EnsureTenantAccess::class,
            'billing.active', EnsurePortalRole::class.':supplier',
        ])->prefix('portal/supplier')->name('portal.supplier.')->group(function (): void {
            require base_path('routes/click-track-supplier.php');
        });

        Route::prefix('api/v1')->middleware([LogApiRequest::class])->group(function (): void {
            require base_path('routes/click-track-api.php');
        });

        $router->getRoutes()->refreshNameLookups();
    }
}
