<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the central (marketing) host, only super-admin login is permitted.
 * Tenant users must use their dedicated subdomain.
 */
class EnsureCentralLoginOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! TenantResolver::isCentralHost($request->getHost())) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && ! $user->isSuperAdmin()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $account = $user->resolveAccount();
            if ($account) {
                return redirect()->away(TenantResolver::portalUrl($account, '/login'))
                    ->with('status', 'Please sign in on your partner platform domain.');
            }

            abort(403, 'Sign in on your partner platform domain.');
        }

        return $next($request);
    }
}
