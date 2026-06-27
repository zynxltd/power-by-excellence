<?php

namespace App\Http\Middleware;

use App\Support\Auth\SignupVerification;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSignupComplete
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || SignupVerification::isComplete($user)) {
            return $next($request);
        }

        if (! $request->routeIs(
            'verification.notice',
            'verification.verify',
            'verification.send',
            'verification.phone',
            'verification.phone.send',
            'verification.phone.verify',
            'verification.address',
            'verification.address.store',
        )) {
            return redirect(SignupVerification::nextRoute($user));
        }

        return $next($request);
    }
}
