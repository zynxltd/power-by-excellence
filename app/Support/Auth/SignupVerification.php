<?php

namespace App\Support\Auth;

use App\Models\User;

class SignupVerification
{
    public static function phoneVerificationEnabled(): bool
    {
        return app(\App\Services\Auth\PhoneVerificationService::class)->isEnabled();
    }

    public static function emailVerificationEnabled(): bool
    {
        return (bool) config('messaging.email_verification_enabled', false);
    }

    public static function addressVerificationEnabled(): bool
    {
        return (bool) config('messaging.address_verification_enabled', false);
    }

    public static function isRequired(User $user): bool
    {
        return ! $user->isSuperAdmin();
    }

    public static function isComplete(User $user): bool
    {
        if (! self::isRequired($user)) {
            return true;
        }

        if (self::emailVerificationEnabled() && ! $user->hasVerifiedEmail()) {
            return false;
        }

        if (self::phoneVerificationEnabled() && ! $user->hasVerifiedPhone()) {
            return false;
        }

        if (self::addressVerificationEnabled() && ! $user->hasVerifiedAddress()) {
            return false;
        }

        return true;
    }

    public static function nextRoute(User $user): ?string
    {
        if (! self::isRequired($user)) {
            return null;
        }

        if (self::emailVerificationEnabled() && ! $user->hasVerifiedEmail()) {
            return route('verification.notice', absolute: false);
        }

        if (self::phoneVerificationEnabled() && ! $user->hasVerifiedPhone()) {
            return route('verification.phone', absolute: false);
        }

        if (self::addressVerificationEnabled() && ! $user->hasVerifiedAddress()) {
            return route('verification.address', absolute: false);
        }

        return null;
    }

    public static function redirectToNext(User $user, ?string $fallback = null)
    {
        $next = self::nextRoute($user);

        return redirect()->intended($next ?? ($fallback ?? route('dashboard', absolute: false)));
    }
}
