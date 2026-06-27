<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictMarketingToCentralHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (TenantResolver::isCentralHost($request->getHost())) {
            return $next($request);
        }

        if ($request->routeIs('help.index', 'help.show')) {
            return $next($request);
        }

        if ($request->user()) {
            $user = $request->user();

            if ($user->isBuyerPortal()) {
                return redirect()->route('portal.buyer.dashboard');
            }

            if ($user->isSupplierPortal()) {
                return redirect()->route('portal.supplier.dashboard');
            }

            return redirect()->route('dashboard');
        }

        return redirect()->route('login');
    }
}
