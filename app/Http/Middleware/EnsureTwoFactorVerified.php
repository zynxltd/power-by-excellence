<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Security\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $twoFactor = app(TwoFactorService::class);

        if ($this->isEnrollmentExemptRoute($request)) {
            return $next($request);
        }

        if ($user->two_factor_enabled) {
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

        if ($twoFactor->requiresEnrollment($user) && ! $twoFactor->isWithinGracePeriod($user)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Two-factor enrollment required.'], 403);
            }

            return redirect()
                ->route('profile.edit')
                ->with('warning', 'Two-factor authentication is required. Enable it on your profile to continue.');
        }

        return $next($request);
    }

    protected function isEnrollmentExemptRoute(Request $request): bool
    {
        return $request->routeIs(
            'profile.edit',
            'profile.update',
            'profile.preferences',
            'profile.two-factor.enable',
            'profile.two-factor.confirm',
            'profile.two-factor.disable',
            'logout',
        );
    }
}
