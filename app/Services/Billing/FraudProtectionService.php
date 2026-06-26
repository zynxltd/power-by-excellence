<?php

namespace App\Services\Billing;

use App\Models\Account;

class FraudProtectionService
{
    public function plan(Account $account): string
    {
        $plan = $account->settings['subscription_plan'] ?? 'starter';

        return array_key_exists($plan, config('fraud_protection.plans', [])) ? $plan : 'starter';
    }

    /**
     * @return array<string, mixed>
     */
    public function planConfig(Account $account): array
    {
        return config('fraud_protection.plans.'.$this->plan($account), config('fraud_protection.plans.starter'));
    }

    public function adminOverride(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->isSuperAdmin();
    }

    public function isPlanEntitled(Account $account): bool
    {
        $plan = $this->planConfig($account);
        $fraud = $account->settings['fraud_protection'] ?? [];

        if ($plan['fraud_included'] ?? false) {
            return true;
        }

        return (bool) ($fraud['enabled'] ?? false);
    }

    public function isEntitled(Account $account): bool
    {
        return $this->adminOverride() || $this->isPlanEntitled($account);
    }

    public function monthlyCap(Account $account): ?int
    {
        $fraud = $account->settings['fraud_protection'] ?? [];
        if (isset($fraud['monthly_cap'])) {
            $cap = $fraud['monthly_cap'];

            return $cap === null || $cap === '' ? null : (int) $cap;
        }

        $cap = $this->planConfig($account)['validated_leads_cap'] ?? null;

        return $cap === null ? null : (int) $cap;
    }

    public function supportsUrlScanner(Account $account): bool
    {
        return false;
    }

    public function supportsResidentialProxy(Account $account): bool
    {
        if ($this->adminOverride()) {
            return true;
        }

        if (! $this->isPlanEntitled($account)) {
            return false;
        }

        return (bool) ($this->planConfig($account)['residential_proxy'] ?? false);
    }

    public function usageCount(Account $account): int
    {
        $this->resetUsageIfNewPeriod($account);

        return (int) (($account->settings['fraud_protection'] ?? [])['usage_count'] ?? 0);
    }

    public function canValidateLead(Account $account): bool
    {
        if ($this->adminOverride()) {
            return true;
        }

        if (! $this->isPlanEntitled($account)) {
            return false;
        }

        $cap = $this->monthlyCap($account);
        if ($cap === null) {
            return true;
        }

        return $this->usageCount($account) < $cap;
    }

    public function recordValidatedLead(Account $account): void
    {
        if (! $this->isPlanEntitled($account)) {
            return;
        }

        $this->resetUsageIfNewPeriod($account);

        $settings = $account->settings ?? [];
        $fraud = $settings['fraud_protection'] ?? [];
        $fraud['usage_count'] = (int) ($fraud['usage_count'] ?? 0) + 1;
        $fraud['usage_period'] = now()->format('Y-m');
        $settings['fraud_protection'] = $fraud;

        $account->update(['settings' => $settings]);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Account $account): array
    {
        $plan = $this->plan($account);
        $planConfig = $this->planConfig($account);
        $cap = $this->monthlyCap($account);
        $usage = $this->usageCount($account);
        $planEntitled = $this->isPlanEntitled($account);
        $adminOverride = $this->adminOverride();

        return [
            'plan' => $plan,
            'plan_label' => $planConfig['label'] ?? ucfirst($plan),
            'entitled' => $planEntitled || $adminOverride,
            'plan_entitled' => $planEntitled,
            'admin_override' => $adminOverride,
            'included' => (bool) ($planConfig['fraud_included'] ?? false),
            'addon_available' => ! ($planConfig['fraud_included'] ?? false),
            'addon_price' => $planConfig['addon_price'] ?? null,
            'can_validate' => $this->canValidateLead($account),
            'usage_count' => $usage,
            'monthly_cap' => $cap,
            'usage_percent' => $cap ? min(100, round(($usage / max(1, $cap)) * 100, 1)) : null,
            'supports_url_scanner' => $this->supportsUrlScanner($account),
            'supports_residential_proxy' => $this->supportsResidentialProxy($account),
            'cap_reached' => ! $adminOverride && $planEntitled && $cap !== null && $usage >= $cap,
        ];
    }

    /**
     * Apply plan defaults to account settings (validation integration + fraud flags).
     *
     * @return array<string, mixed>
     */
    public function provisionSettingsForPlan(array $settings, string $plan, bool $fraudAddonEnabled = false): array
    {
        $planConfig = config('fraud_protection.plans.'.$plan, config('fraud_protection.plans.starter'));
        $included = (bool) ($planConfig['fraud_included'] ?? false);
        $entitled = $included || $fraudAddonEnabled;

        $settings['subscription_plan'] = $plan;
        $settings['fraud_protection'] = array_merge($settings['fraud_protection'] ?? [], [
            'enabled' => $entitled,
            'included' => $included,
        ]);

        if ($entitled) {
            $integration = $settings['validation_integration'] ?? [];
            $settings['validation_integration'] = array_merge($integration, [
                'enabled' => true,
                'provider' => 'ipqs',
                'email_validation' => true,
                'hlr_validation' => true,
                'ip_validation' => true,
                'url_validation' => false,
                'quarantine_on_fail' => $integration['quarantine_on_fail'] ?? true,
            ]);
        }

        return $settings;
    }

    protected function resetUsageIfNewPeriod(Account $account): void
    {
        $period = ($account->settings['fraud_protection'] ?? [])['usage_period'] ?? null;
        $current = now()->format('Y-m');

        if ($period === $current) {
            return;
        }

        $settings = $account->settings ?? [];
        $fraud = $settings['fraud_protection'] ?? [];
        $fraud['usage_count'] = 0;
        $fraud['usage_period'] = $current;
        $settings['fraud_protection'] = $fraud;

        $account->update(['settings' => $settings]);
        $account->refresh();
    }
}
