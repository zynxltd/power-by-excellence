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

        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('login');
    }
}
