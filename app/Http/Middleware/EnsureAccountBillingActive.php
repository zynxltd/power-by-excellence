<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Services\Billing\AccountBillingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountBillingActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $account = $request->attributes->get('account');

        if (! $account || ! $user) {
            return $next($request);
        }

        if ($user->role === UserRole::SuperAdmin && $request->routeIs('accounts.*')) {
            return $next($request);
        }

        $status = app(AccountBillingService::class)->resolveStatus($account);

        if ($status !== AccountBillingService::STATUS_LOCKED) {
            return $next($request);
        }

        $allowed = [
            'billing.index',
            'billing.show',
            'billing.top-up',
            'billing.lock',
            'settings.edit',
            'settings.update',
            'profile.edit',
            'profile.update',
            'profile.preferences',
            'logout',
            'portal.buyer.billing',
            'portal.supplier.billing',
        ];

        if ($request->route() && in_array($request->route()->getName(), $allowed, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Account billing is locked. Please contact your platform administrator.',
                'billing_status' => $status,
            ], 402);
        }

        return redirect()->route('billing.lock');
    }
}
