<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Messaging\MessagingGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PhoneVerificationService
{
    public function __construct(
        protected MessagingGateway $messaging,
    ) {}

    public function isEnabled(): bool
    {
        $override = config('messaging.phone_verification_enabled');

        if ($override !== null && $override !== '') {
            return filter_var($override, FILTER_VALIDATE_BOOLEAN);
        }

        $provider = config('messaging.sms_provider', 'log');

        if ($provider === 'log') {
            return false;
        }

        if ($provider === 'twilio') {
            return filled(config('messaging.twilio.sid'))
                && filled(config('messaging.twilio.token'))
                && filled(config('messaging.twilio.from'));
        }

        if ($provider === 'vonage') {
            return filled(config('messaging.vonage.key'))
                && filled(config('messaging.vonage.secret'));
        }

        return false;
    }

    public function sendCode(User $user, string $phone): void
    {
        if (! $this->isEnabled()) {
            throw ValidationException::withMessages([
                'phone' => 'Phone verification is not available until SMS is configured.',
            ]);
        }
        $normalized = $this->normalizePhone($phone);

        $code = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($user), [
            'code' => $code,
            'phone' => $normalized,
        ], now()->addMinutes(10));

        $sent = $this->messaging->sendSms(
            $normalized,
            "Your PowerByExcellence verification code is {$code}. It expires in 10 minutes."
        );

        if (! $sent) {
            throw ValidationException::withMessages([
                'phone' => 'We could not send a verification code. Please try again shortly.',
            ]);
        }

        $user->forceFill(['phone' => $normalized])->save();
    }

    public function verify(User $user, string $code): void
    {
        $payload = Cache::get($this->cacheKey($user));

        if (! is_array($payload) || ! isset($payload['code'], $payload['phone'])) {
            throw ValidationException::withMessages([
                'code' => 'No verification code is pending. Request a new code.',
            ]);
        }

        if (! hash_equals($payload['code'], trim($code))) {
            throw ValidationException::withMessages([
                'code' => 'The verification code is incorrect.',
            ]);
        }

        $user->forceFill([
            'phone' => $payload['phone'],
            'phone_verified_at' => now(),
        ])->save();

        Cache::forget($this->cacheKey($user));
    }

    public function normalizePhone(string $phone): string
    {
        $trimmed = trim($phone);
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            throw ValidationException::withMessages([
                'phone' => 'Enter a valid phone number.',
            ]);
        }

        if (str_starts_with($trimmed, '0') && ! str_starts_with($trimmed, '00')) {
            $digits = '44'.ltrim($digits, '0');
        }

        if (strlen($digits) < 10) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a valid phone number with country code.',
            ]);
        }

        return '+'.$digits;
    }

    protected function cacheKey(User $user): string
    {
        return 'signup_phone_verification:'.$user->id;
    }
}
