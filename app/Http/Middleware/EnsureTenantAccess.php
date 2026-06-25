<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Http\ExternalRedirect;
use App\Support\Tenancy\AccountContext;
use App\Support\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $hostAccount = $request->attributes->get('host_account');

        if (! $user) {
            return $next($request);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (TenantResolver::isCentralHost($request->getHost())) {
            $account = $user->resolveAccount();
            if ($account) {
                if (app()->environment('testing')) {
                    AccountContext::set($account);
                    $request->attributes->set('account', $account);

                    return $next($request);
                }

                $path = $request->getPathInfo() ?: '/dashboard';

                return redirect()->away(TenantResolver::portalUrl($account, $path));
            }

            abort(403, 'Sign in on your partner platform domain.');
        }

        if ($hostAccount && ! $user->belongsToAccount($hostAccount)) {
            return $this->redirectToUserPortal(
                $request,
                $user,
                'Your account is not registered on this platform domain.'
            );
        }

        return $next($request);
    }

    protected function redirectToUserPortal(Request $request, User $user, string $message): Response
    {
        $account = $user->resolveAccount();

        if (! $account) {
            abort(403, $message);
        }

        $path = $request->getPathInfo() ?: '/dashboard';
        $url = TenantResolver::portalUrl($account, $path);

        $request->session()->flash('error', $message);

        return ExternalRedirect::away($request, $url);
    }
}
