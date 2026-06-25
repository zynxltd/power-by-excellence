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

        $allowed = match ($role) {
            'buyer' => $user?->role === UserRole::BuyerPortal,
            'supplier' => $user?->role === UserRole::SupplierPortal,
            'admin' => in_array($user?->role, [UserRole::AccountAdmin, UserRole::Staff, UserRole::SuperAdmin], true),
            default => false,
        };

        abort_unless($allowed, 403);

        return $next($request);
    }
}
