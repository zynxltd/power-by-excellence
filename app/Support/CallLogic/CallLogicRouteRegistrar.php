<?php

namespace App\Support\CallLogic;

use App\Http\Middleware\EnsurePortalRole;
use App\Http\Middleware\EnsureProductEnabled;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\SetAccountFromUser;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

/**
 * Registers Call Logic routes for tests and optional boot-time wiring.
 * Integration Lead: prefer require of route manifest files in web.php / api.php.
 */
final class CallLogicRouteRegistrar
{
    public static function register(): void
    {
        /** @var Router $router */
        $router = app('router');

        if ($router->getRoutes()->getByName('call-logic.settings.edit')) {
            return;
        }

        $router->aliasMiddleware('product.enabled', EnsureProductEnabled::class);

        require base_path('routes/call-logic.php');

        Route::middleware([
            'web',
            'auth',
            'verified',
            'signup.complete',
            SetAccountFromUser::class,
            EnsureTenantAccess::class,
            'billing.active',
            EnsurePortalRole::class.':admin',
            'module.access',
        ])->group(function (): void {
            callLogicAdminRoutes();
        });

        Route::middleware([
            'web',
            'auth',
            'verified',
            'signup.complete',
            SetAccountFromUser::class,
            EnsureTenantAccess::class,
            'billing.active',
            EnsurePortalRole::class.':buyer',
        ])->prefix('portal/buyer')->group(function (): void {
            require base_path('routes/call-logic-portal.php');
        });

        Route::prefix('api/v1')->middleware([LogApiRequest::class])->group(function (): void {
            require base_path('routes/call-logic-api.php');
        });

        $router->getRoutes()->refreshNameLookups();
    }
}
