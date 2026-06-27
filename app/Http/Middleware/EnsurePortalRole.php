<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if ($request->routeIs('impersonate.stop') && ($request->session()->get('impersonator_id') || $request->session()->get('god_mode'))) {
            return $next($request);
        }

        $allowed = match ($role) {
            'buyer' => $user?->role === UserRole::BuyerPortal,
            'supplier' => $user?->role === UserRole::SupplierPortal,
            'admin' => in_array($user?->role, [UserRole::AccountAdmin, UserRole::Staff, UserRole::SuperAdmin], true),
            default => false,
        };

        if ($allowed) {
            return $next($request);
        }

        if ($user?->role === UserRole::BuyerPortal) {
            return redirect()
                ->route('portal.buyer.dashboard')
                ->with('error', 'Buyer portal accounts cannot access admin pages.');
        }

        if ($user?->role === UserRole::SupplierPortal) {
            return redirect()
                ->route('portal.supplier.dashboard')
                ->with('error', 'Supplier portal accounts cannot access admin pages. Use the supplier dashboard instead.');
        }
        abort(403);
    }
}
