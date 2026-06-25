<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\AccountContext;
use App\Support\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAccountFromHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = TenantResolver::resolveFromHost($request->getHost());

        if ($account) {
            AccountContext::set($account);
            $request->attributes->set('host_account', $account);
            $request->attributes->set('account', $account);
        }

        TenantResolver::forceRootUrl($account);

        return $next($request);
    }
}
