<?php

namespace App\Support\CallLogic;

use App\Http\Controllers\Admin\CallRecordingController;
use App\Http\Controllers\Admin\TrackingNumberController;
use App\Http\Middleware\EnsurePortalRole;
use App\Http\Middleware\EnsureProductEnabled;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\SetAccountFromUser;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

final class CallLogicRouteRegistrar
{
    private static bool $apiRoutesLoaded = false;

    public static function register(): void
    {
        /** @var Router $router */
        $router = app('router');

        if (! $router->getRoutes()->getByName('sdk.calls')) {
            $router->aliasMiddleware('product.enabled', EnsureProductEnabled::class);
            require base_path('routes/call-logic.php');
        }

        if (! $router->getRoutes()->getByName('call-logic.settings.edit')) {
            Route::middleware([
                'web', 'auth', 'verified', 'signup.complete',
                SetAccountFromUser::class, EnsureTenantAccess::class,
                'billing.active', EnsurePortalRole::class.':admin', 'module.access',
            ])->group(function (): void {
                callLogicAdminRoutes();
            });
        } elseif (! $router->getRoutes()->getByName('call-logic.tracking-numbers.search')) {
            Route::middleware([
                'web', 'auth', 'verified', 'signup.complete',
                SetAccountFromUser::class, EnsureTenantAccess::class,
                'billing.active', EnsurePortalRole::class.':admin', 'module.access',
            ])->group(function (): void {
                Route::prefix('call-logic')->middleware('product.enabled:call_logic')->group(function (): void {
                    Route::post('tracking-numbers/search', [TrackingNumberController::class, 'search'])->name('call-logic.tracking-numbers.search');
                    Route::post('tracking-numbers/purchase', [TrackingNumberController::class, 'purchase'])->name('call-logic.tracking-numbers.purchase');
                });
            });
        } elseif (! $router->getRoutes()->getByName('call-logic.recordings.play')) {
            Route::middleware([
                'web', 'auth', 'verified', 'signup.complete',
                SetAccountFromUser::class, EnsureTenantAccess::class,
                'billing.active', EnsurePortalRole::class.':admin', 'module.access',
            ])->group(function (): void {
                Route::prefix('call-logic')->middleware('product.enabled:call_logic')->group(function (): void {
                    Route::get('recordings/{recording}/play', [CallRecordingController::class, 'play'])->name('call-logic.recordings.play');
                });
            });
        }

        if (! $router->getRoutes()->getByName('portal.buyer.calls')) {
            Route::middleware([
                'web', 'auth', 'verified', 'signup.complete',
                SetAccountFromUser::class, EnsureTenantAccess::class,
                'billing.active', EnsurePortalRole::class.':buyer',
            ])->prefix('portal/buyer')->group(function (): void {
                require base_path('routes/call-logic-portal.php');
            });
        }

        if (! self::$apiRoutesLoaded) {
            Route::prefix('api/v1')->middleware([LogApiRequest::class])->group(function (): void {
                require base_path('routes/call-logic-api.php');
            });
            self::$apiRoutesLoaded = true;
        }

        $router->getRoutes()->refreshNameLookups();
    }
}
