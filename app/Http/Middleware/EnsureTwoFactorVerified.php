<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->two_factor_enabled) {
            return $next($request);
        }

        if ($request->session()->get('two_factor_verified') === $user->id) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Two-factor authentication required.'], 403);
        }

        $request->session()->put('login.id', $user->id);
        auth()->logout();

        return redirect()->route('two-factor.login');
    }
}
