<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\AdminModules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin() || $user->role === UserRole::AccountAdmin) {
            return $next($request);
        }

        if (! in_array($user->role, [UserRole::Staff], true)) {
            return $next($request);
        }

        $module = AdminModules::moduleForRoute($request->route()?->getName());

        if ($module && ! $user->hasModuleAccess($module)) {
            abort(403, 'You do not have access to this module.');
        }

        return $next($request);
    }
}
