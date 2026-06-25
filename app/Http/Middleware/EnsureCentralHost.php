<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict routes to the central platform host (not tenant subdomains).
 */
class EnsureCentralHost
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            TenantResolver::isCentralHost($request->getHost()),
            403,
            'This area is only available on the central platform.'
        );

        return $next($request);
    }
}
