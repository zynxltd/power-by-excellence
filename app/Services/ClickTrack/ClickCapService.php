<?php

namespace App\Services\ClickTrack;

use App\Models\Account;
use App\Models\TrackingClick;
use App\Models\TrackingConversion;
use App\Models\TrackingLink;
use Carbon\Carbon;

class ClickCapService
{
    public function softLimitPct(?Account $account): int
    {
        $settings = $account?->settings['click_track'] ?? [];

        return max(1, min(99, (int) ($settings['cap_soft_limit_pct'] ?? config('click_track.cap_soft_limit_pct', 80))));
    }

    public function linkHardCapReached(TrackingLink $link): bool
    {
        return $this->enforceLinkCap($link)['hard'];
    }

    public function linkSoftCapReached(TrackingLink $link): bool
    {
        $result = $this->enforceLinkCap($link);

        return $result['soft'] && ! $result['hard'];
    }

    /** @deprecated Use linkHardCapReached() */
    public function linkCapReached(TrackingLink $link): bool
    {
        return $this->linkHardCapReached($link);
    }

    public function conversionCapReached(TrackingLink $link): bool
    {
        return $this->enforceConversionCap($link)['hard'];
    }

    public function accountHardCapReached(Account $account): bool
    {
        return app(ClickTrackEntitlementService::class)->capReached($account)
            || $this->accountHourlyHardCapReached($account);
    }

    public function accountHourlyHardCapReached(Account $account): bool
    {
        $hourlyCap = $this->accountHourlyCap($account);
        if (! $hourlyCap) {
            return false;
        }

        return $this->clicksThisHourForAccount($account) >= $hourlyCap;
    }

    /**
     * @return array{soft: bool, hard: bool, periods: array<string, mixed>}
     */
    public function enforceLinkCap(TrackingLink $link): array
    {
        $softPct = $this->softLimitPct($link->account);
        $periods = [];
        $soft = false;
        $hard = false;

        foreach ($this->clickCapPeriods($link) as $key => $cap) {
            if (! $cap) {
                continue;
            }

            $used = match ($key) {
                'hourly' => $this->clicksThisHour($link),
                'daily' => $this->clicksToday($link),
                'monthly' => $this->clicksThisMonth($link),
                default => 0,
            };

            $status = $this->periodStatus($used, $cap, $softPct);
            $periods[$key] = $status;
            $soft = $soft || $status['soft'];
            $hard = $hard || $status['hard'];
        }

        return compact('soft', 'hard', 'periods');
    }

    /**
     * @return array{soft: bool, hard: bool}
     */
    public function enforceConversionCap(TrackingLink $link): array
    {
        $config = $link->config ?? [];
        $softPct = $this->softLimitPct($link->account);
        $soft = false;
        $hard = false;

        foreach (['hourly' => 'conversion_cap_hourly', 'daily' => 'conversion_cap_daily'] as $period => $configKey) {
            $cap = (int) ($config[$configKey] ?? 0);
            if ($cap <= 0) {
                continue;
            }

            $used = match ($period) {
                'hourly' => $this->conversionsThisHour($link),
                'daily' => $this->conversionsToday($link),
                default => 0,
            };

            $status = $this->periodStatus($used, $cap, $softPct);
            $soft = $soft || $status['soft'];
            $hard = $hard || $status['hard'];
        }

        return compact('soft', 'hard');
    }

    /**
     * @return array{hourly: int|null, daily: int|null, monthly: int|null, conversion_hourly: int|null, conversion_daily: int|null}
     */
    public function capsForLink(TrackingLink $link): array
    {
        $config = $link->config ?? [];

        return [
            'hourly' => isset($config['cap_hourly']) ? (int) $config['cap_hourly'] : null,
            'daily' => isset($config['cap_daily']) ? (int) $config['cap_daily'] : null,
            'monthly' => isset($config['cap_monthly']) ? (int) $config['cap_monthly'] : null,
            'conversion_hourly' => isset($config['conversion_cap_hourly']) ? (int) $config['conversion_cap_hourly'] : null,
            'conversion_daily' => isset($config['conversion_cap_daily']) ? (int) $config['conversion_cap_daily'] : null,
        ];
    }

    /**
     * @return array<string, int|null>
     */
    protected function clickCapPeriods(TrackingLink $link): array
    {
        $caps = $this->capsForLink($link);

        return [
            'hourly' => $caps['hourly'],
            'daily' => $caps['daily'],
            'monthly' => $caps['monthly'],
        ];
    }

    public function accountHourlyCap(Account $account): ?int
    {
        $settings = $account->settings['click_track'] ?? [];
        $cap = $settings['cap_hourly'] ?? null;

        return $cap === null || $cap === '' ? null : (int) $cap;
    }

    public function clicksThisHour(TrackingLink $link): int
    {
        return TrackingClick::withoutGlobalScopes()
            ->where('tracking_link_id', $link->id)
            ->where('clicked_at', '>=', now()->startOfHour())
            ->count();
    }

    public function clicksThisHourForAccount(Account $account): int
    {
        return TrackingClick::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('clicked_at', '>=', now()->startOfHour())
            ->count();
    }

    public function clicksToday(TrackingLink $link): int
    {
        return TrackingClick::withoutGlobalScopes()
            ->where('tracking_link_id', $link->id)
            ->where('clicked_at', '>=', Carbon::today())
            ->count();
    }

    public function clicksThisMonth(TrackingLink $link): int
    {
        return TrackingClick::withoutGlobalScopes()
            ->where('tracking_link_id', $link->id)
            ->where('clicked_at', '>=', now()->startOfMonth())
            ->count();
    }

    public function conversionsThisHour(TrackingLink $link): int
    {
        return TrackingConversion::withoutGlobalScopes()
            ->where('tracking_link_id', $link->id)
            ->where('created_at', '>=', now()->startOfHour())
            ->count();
    }

    public function conversionsToday(TrackingLink $link): int
    {
        return TrackingConversion::withoutGlobalScopes()
            ->where('tracking_link_id', $link->id)
            ->where('created_at', '>=', Carbon::today())
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function usageForLink(TrackingLink $link): array
    {
        $caps = $this->capsForLink($link);
        $enforcement = $this->enforceLinkCap($link);
        $conversion = $this->enforceConversionCap($link);
        $softPct = $this->softLimitPct($link->account);

        return [
            'link_id' => $link->id,
            'link_name' => $link->name,
            'caps' => $caps,
            'soft_limit_pct' => $softPct,
            'clicks_hour' => $this->clicksThisHour($link),
            'clicks_today' => $this->clicksToday($link),
            'clicks_month' => $this->clicksThisMonth($link),
            'conversions_hour' => $this->conversionsThisHour($link),
            'conversions_today' => $this->conversionsToday($link),
            'periods' => $enforcement['periods'],
            'click_soft_cap_reached' => $enforcement['soft'],
            'click_cap_reached' => $enforcement['hard'],
            'conversion_soft_cap_reached' => $conversion['soft'],
            'conversion_cap_reached' => $conversion['hard'],
            'click_hourly_pct' => $this->pct($this->clicksThisHour($link), $caps['hourly']),
            'click_daily_pct' => $this->pct($this->clicksToday($link), $caps['daily']),
            'click_monthly_pct' => $this->pct($this->clicksThisMonth($link), $caps['monthly']),
            'conversion_daily_pct' => $this->pct($this->conversionsToday($link), $caps['conversion_daily']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accountUsage(Account $account): array
    {
        $entitlement = app(ClickTrackEntitlementService::class);
        $clicksCap = $entitlement->clicksCap($account);
        $conversionsCap = $entitlement->conversionsCap($account);
        $clicksUsed = $entitlement->clicksUsed($account);
        $conversionsUsed = $entitlement->conversionsUsed($account);
        $hourlyCap = $this->accountHourlyCap($account);
        $clicksHour = $this->clicksThisHourForAccount($account);
        $softPct = $this->softLimitPct($account);

        $clicksStatus = $this->periodStatus($clicksUsed, $clicksCap, $softPct);
        $hourlyStatus = $this->periodStatus($clicksHour, $hourlyCap, $softPct);
        $conversionsStatus = $this->periodStatus($conversionsUsed, $conversionsCap, $softPct);

        return [
            'clicks_used' => $clicksUsed,
            'clicks_cap' => $clicksCap,
            'clicks_pct' => $clicksStatus['pct'],
            'clicks_soft' => $clicksStatus['soft'],
            'clicks_hard' => $clicksStatus['hard'],
            'clicks_hour' => $clicksHour,
            'cap_hourly' => $hourlyCap,
            'clicks_hour_pct' => $hourlyStatus['pct'],
            'clicks_hour_soft' => $hourlyStatus['soft'],
            'clicks_hour_hard' => $hourlyStatus['hard'],
            'conversions_used' => $conversionsUsed,
            'conversions_cap' => $conversionsCap,
            'conversions_pct' => $conversionsStatus['pct'],
            'conversions_soft' => $conversionsStatus['soft'],
            'conversions_hard' => $conversionsStatus['hard'],
            'soft_limit_pct' => $softPct,
        ];
    }

    public function linksNearSoftCap(int $accountId, ?int $softPct = null): int
    {
        $account = Account::find($accountId);
        $softPct ??= $this->softLimitPct($account);

        return TrackingLink::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('status', 'active')
            ->get()
            ->filter(function (TrackingLink $link) use ($softPct) {
                $usage = $this->usageForLink($link);

                return $usage['click_soft_cap_reached'] || $usage['conversion_soft_cap_reached'];
            })
            ->count();
    }

    public function maxAccountUsagePct(Account $account): float
    {
        $usage = $this->accountUsage($account);
        $values = array_filter([
            $usage['clicks_pct'],
            $usage['clicks_hour_pct'],
            $usage['conversions_pct'],
        ], fn ($v) => $v !== null);

        return $values === [] ? 0.0 : (float) max($values);
    }

    /**
     * @return array{used: int, cap: int|null, pct: int|null, soft: bool, hard: bool}
     */
    protected function periodStatus(int $used, ?int $cap, int $softPct): array
    {
        if ($cap === null || $cap <= 0) {
            return ['used' => $used, 'cap' => $cap, 'pct' => null, 'soft' => false, 'hard' => false];
        }

        $pct = min(100, (int) round(($used / max(1, $cap)) * 100));
        $softThreshold = (int) floor($cap * $softPct / 100);

        return [
            'used' => $used,
            'cap' => $cap,
            'pct' => $pct,
            'soft' => $used >= $softThreshold,
            'hard' => $used >= $cap,
        ];
    }

    protected function pct(int $used, ?int $cap): ?int
    {
        if ($cap === null || $cap <= 0) {
            return null;
        }

        return min(100, (int) round(($used / max(1, $cap)) * 100));
    }
}
