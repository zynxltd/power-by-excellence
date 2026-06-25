<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = Auth::user();

        if ($user?->isSuspended()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account has been suspended. Contact your platform administrator.',
            ]);
        }

        $hostAccount = TenantResolver::resolveFromHost($this->getHost());

        if (TenantResolver::isCentralHost($this->getHost())) {
            if (! $user?->isSuperAdmin()) {
                Auth::logout();
                $account = $user?->resolveAccount();

                throw ValidationException::withMessages([
                    'email' => $account
                        ? 'Partner platforms sign in at '.$account->portalUrl('/login')
                        : 'Use your dedicated partner platform domain to sign in.',
                ]);
            }
        } elseif ($user?->isSuperAdmin()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Super admin accounts must sign in at https://'.TenantResolver::baseDomain().'/login',
            ]);
        } elseif ($hostAccount && $user && ! $user->belongsToAccount($hostAccount)) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account is not registered on '.($hostAccount->brand_name ?: $hostAccount->name).'.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
