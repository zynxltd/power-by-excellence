<?php

namespace App\Services\ClickTrack;

use App\Models\Account;
use App\Models\TrackingClick;
use App\Models\TrackingConversion;
use App\Models\TrackingImpression;
use Carbon\Carbon;

class ClickTrackEntitlementService
{
    public function plan(Account $account): string
    {
        $plan = $account->settings['subscription_plan'] ?? 'starter';

        return array_key_exists($plan, config('click_track.plans', [])) ? $plan : 'starter';
    }

    /**
     * @return array<string, mixed>
     */
    public function planConfig(Account $account): array
    {
        return config('click_track.plans.'.$this->plan($account), config('click_track.plans.starter'));
    }

    public function adminOverride(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->isSuperAdmin();
    }

    public function isPlanEntitled(Account $account): bool
    {
        $plan = $this->planConfig($account);
        $settings = $account->settings['click_track'] ?? [];

        if ($plan['included'] ?? false) {
            return true;
        }

        if (! ($plan['addon_available'] ?? false)) {
            return false;
        }

        return (bool) ($settings['enabled'] ?? false);
    }

    public function isEntitled(?Account $account): bool
    {
        if (! $account) {
            return false;
        }

        return $this->adminOverride() || $this->isPlanEntitled($account);
    }

    public function clicksCap(Account $account): ?int
    {
        $settings = $account->settings['click_track'] ?? [];
        if (array_key_exists('clicks_cap', $settings)) {
            $cap = $settings['clicks_cap'];

            return $cap === null || $cap === '' ? null : (int) $cap;
        }

        $cap = $this->planConfig($account)['clicks_cap'] ?? null;

        return $cap === null ? null : (int) $cap;
    }

    public function conversionsCap(Account $account): ?int
    {
        $settings = $account->settings['click_track'] ?? [];
        if (array_key_exists('conversions_cap', $settings)) {
            $cap = $settings['conversions_cap'];

            return $cap === null || $cap === '' ? null : (int) $cap;
        }

        $cap = $this->planConfig($account)['conversions_cap'] ?? null;

        return $cap === null ? null : (int) $cap;
    }

    public function usagePeriodStart(Account $account): Carbon
    {
        $period = $account->settings['click_track']['usage_period_start'] ?? null;

        return $period ? Carbon::parse($period)->startOfDay() : now()->startOfMonth();
    }

    public function clicksUsed(Account $account, ?Carbon $since = null): int
    {
        $since ??= $this->usagePeriodStart($account);

        return TrackingClick::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('clicked_at', '>=', $since)
            ->count();
    }

    public function conversionsUsed(Account $account, ?Carbon $since = null): int
    {
        $since ??= $this->usagePeriodStart($account);

        return TrackingConversion::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('created_at', '>=', $since)
            ->count();
    }

    public function capReached(Account $account): bool
    {
        $clicksCap = $this->clicksCap($account);
        if ($clicksCap !== null && $this->clicksUsed($account) >= $clicksCap) {
            return true;
        }

        $conversionsCap = $this->conversionsCap($account);

        return $conversionsCap !== null && $this->conversionsUsed($account) >= $conversionsCap;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(?Account $account): array
    {
        if (! $account) {
            return ['entitled' => false];
        }

        $plan = $this->planConfig($account);
        $entitled = $this->isEntitled($account);
        $clicksCap = $this->clicksCap($account);
        $conversionsCap = $this->conversionsCap($account);
        $clicksUsed = $entitled ? $this->clicksUsed($account) : 0;
        $conversionsUsed = $entitled ? $this->conversionsUsed($account) : 0;

        return [
            'entitled' => $entitled,
            'plan' => $this->plan($account),
            'plan_label' => $plan['label'] ?? 'Starter',
            'addon_available' => (bool) ($plan['addon_available'] ?? false),
            'clicks_cap' => $clicksCap,
            'conversions_cap' => $conversionsCap,
            'clicks_used' => $clicksUsed,
            'conversions_used' => $conversionsUsed,
            'clicks_remaining' => $clicksCap === null ? null : max(0, $clicksCap - $clicksUsed),
            'conversions_remaining' => $conversionsCap === null ? null : max(0, $conversionsCap - $conversionsUsed),
            'cap_reached' => $entitled && $this->capReached($account),
            'usage_period_start' => $this->usagePeriodStart($account)->toDateString(),
        ];
    }
}
