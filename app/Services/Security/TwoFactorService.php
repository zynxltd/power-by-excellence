<?php

namespace App\Services\Security;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(protected Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQrCodeUrl(User $user, string $secret): string
    {
        $issuer = config('app.name', 'PowerByExcellence');

        return $this->google2fa->getQRCodeUrl($issuer, $user->email, $secret);
    }

    public function verifyCode(?string $secret, string $code): bool
    {
        if (! $secret || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return $this->google2fa->verifyKey($secret, $code);
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => Str::upper(Str::random(10)))
            ->all();
    }

    public function isStaffUser(User $user): bool
    {
        return in_array($user->role, [UserRole::AccountAdmin, UserRole::Staff], true);
    }

    public function isPortalUser(User $user): bool
    {
        return in_array($user->role, [UserRole::BuyerPortal, UserRole::SupplierPortal], true);
    }

    public function policyAppliesToUser(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        $account = $user->resolveAccount();
        if (! $account) {
            return false;
        }

        $settings = $account->settings ?? [];

        if ($this->isStaffUser($user)) {
            return (bool) ($settings['require_2fa_for_staff'] ?? false);
        }

        if ($this->isPortalUser($user)) {
            return (bool) ($settings['require_2fa_for_portal'] ?? false);
        }

        return false;
    }

    public function requiresEnrollment(User $user): bool
    {
        return $this->policyAppliesToUser($user) && ! $user->two_factor_enabled;
    }

    public function mustKeepTwoFactor(User $user): bool
    {
        return $this->policyAppliesToUser($user) && $user->two_factor_enabled;
    }

    public function isWithinGracePeriod(User $user): bool
    {
        if (! $this->requiresEnrollment($user)) {
            return false;
        }

        $account = $user->resolveAccount();
        if (! $account) {
            return false;
        }

        $settings = $account->settings ?? [];
        $graceDays = (int) ($settings['two_factor_grace_days'] ?? 0);

        if ($graceDays <= 0) {
            return false;
        }

        $enabledAt = $settings[$this->policyEnabledAtKey($user)] ?? null;
        if (! $enabledAt) {
            return false;
        }

        return now()->lt(Carbon::parse($enabledAt)->addDays($graceDays));
    }

    public function graceDeadline(User $user): ?Carbon
    {
        if (! $this->requiresEnrollment($user)) {
            return null;
        }

        $account = $user->resolveAccount();
        if (! $account) {
            return null;
        }

        $settings = $account->settings ?? [];
        $graceDays = (int) ($settings['two_factor_grace_days'] ?? 0);
        $enabledAt = $settings[$this->policyEnabledAtKey($user)] ?? null;

        if ($graceDays <= 0 || ! $enabledAt) {
            return null;
        }

        return Carbon::parse($enabledAt)->addDays($graceDays);
    }

    public function policyEnabledAtKey(User $user): string
    {
        return $this->isPortalUser($user)
            ? 'require_2fa_for_portal_enabled_at'
            : 'require_2fa_for_staff_enabled_at';
    }

    /**
     * @return array{
     *     require_2fa_for_staff: bool,
     *     require_2fa_for_portal: bool,
     *     two_factor_grace_days: int,
     *     require_2fa_for_staff_enabled_at: ?string,
     *     require_2fa_for_portal_enabled_at: ?string
     * }
     */
    public function policySettings(Account $account): array
    {
        $settings = $account->settings ?? [];

        return [
            'require_2fa_for_staff' => (bool) ($settings['require_2fa_for_staff'] ?? false),
            'require_2fa_for_portal' => (bool) ($settings['require_2fa_for_portal'] ?? false),
            'two_factor_grace_days' => (int) ($settings['two_factor_grace_days'] ?? 7),
            'require_2fa_for_staff_enabled_at' => $settings['require_2fa_for_staff_enabled_at'] ?? null,
            'require_2fa_for_portal_enabled_at' => $settings['require_2fa_for_portal_enabled_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function mergePolicySettings(array $settings, array $validated): array
    {
        $staffRequired = (bool) ($validated['require_2fa_for_staff'] ?? false);
        $portalRequired = (bool) ($validated['require_2fa_for_portal'] ?? false);

        if ($staffRequired && ! ($settings['require_2fa_for_staff'] ?? false)) {
            $settings['require_2fa_for_staff_enabled_at'] = now()->toIso8601String();
        }

        if (! $staffRequired) {
            unset($settings['require_2fa_for_staff_enabled_at']);
        }

        if ($portalRequired && ! ($settings['require_2fa_for_portal'] ?? false)) {
            $settings['require_2fa_for_portal_enabled_at'] = now()->toIso8601String();
        }

        if (! $portalRequired) {
            unset($settings['require_2fa_for_portal_enabled_at']);
        }

        $settings['require_2fa_for_staff'] = $staffRequired;
        $settings['require_2fa_for_portal'] = $portalRequired;
        $settings['two_factor_grace_days'] = (int) ($validated['two_factor_grace_days'] ?? $settings['two_factor_grace_days'] ?? 7);

        return $settings;
    }
}
