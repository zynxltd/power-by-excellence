<?php

namespace App\Services\ClickTrack;

use App\Models\TrackingClick;
use App\Models\TrackingConversion;
use App\Models\TrackingLink;
use Carbon\Carbon;

class ClickCapService
{
    public function linkCapReached(TrackingLink $link): bool
    {
        $config = $link->config ?? [];

        if ($daily = (int) ($config['cap_daily'] ?? 0)) {
            $count = TrackingClick::withoutGlobalScopes()
                ->where('tracking_link_id', $link->id)
                ->where('clicked_at', '>=', now()->startOfDay())
                ->count();

            if ($count >= $daily) {
                return true;
            }
        }

        if ($monthly = (int) ($config['cap_monthly'] ?? 0)) {
            $count = TrackingClick::withoutGlobalScopes()
                ->where('tracking_link_id', $link->id)
                ->where('clicked_at', '>=', now()->startOfMonth())
                ->count();

            if ($count >= $monthly) {
                return true;
            }
        }

        return false;
    }

    public function conversionCapReached(TrackingLink $link): bool
    {
        $config = $link->config ?? [];

        if ($daily = (int) ($config['conversion_cap_daily'] ?? 0)) {
            $count = TrackingConversion::withoutGlobalScopes()
                ->where('tracking_link_id', $link->id)
                ->where('created_at', '>=', now()->startOfDay())
                ->count();

            if ($count >= $daily) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{daily: int|null, monthly: int|null, conversion_daily: int|null}
     */
    public function capsForLink(TrackingLink $link): array
    {
        $config = $link->config ?? [];

        return [
            'daily' => isset($config['cap_daily']) ? (int) $config['cap_daily'] : null,
            'monthly' => isset($config['cap_monthly']) ? (int) $config['cap_monthly'] : null,
            'conversion_daily' => isset($config['conversion_cap_daily']) ? (int) $config['conversion_cap_daily'] : null,
        ];
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
        $clicksToday = $this->clicksToday($link);
        $clicksMonth = $this->clicksThisMonth($link);
        $conversionsToday = $this->conversionsToday($link);

        $clickCapReached = ($caps['daily'] !== null && $caps['daily'] > 0 && $clicksToday >= $caps['daily'])
            || ($caps['monthly'] !== null && $caps['monthly'] > 0 && $clicksMonth >= $caps['monthly']);

        $conversionCapReached = $caps['conversion_daily'] !== null
            && $caps['conversion_daily'] > 0
            && $conversionsToday >= $caps['conversion_daily'];

        return [
            'link_id' => $link->id,
            'link_name' => $link->name,
            'caps' => $caps,
            'clicks_today' => $clicksToday,
            'clicks_month' => $clicksMonth,
            'conversions_today' => $conversionsToday,
            'click_cap_reached' => $clickCapReached,
            'conversion_cap_reached' => $conversionCapReached,
            'click_daily_pct' => $caps['daily'] ? min(100, (int) round(($clicksToday / max(1, $caps['daily'])) * 100)) : null,
            'click_monthly_pct' => $caps['monthly'] ? min(100, (int) round(($clicksMonth / max(1, $caps['monthly'])) * 100)) : null,
            'conversion_daily_pct' => $caps['conversion_daily'] ? min(100, (int) round(($conversionsToday / max(1, $caps['conversion_daily'])) * 100)) : null,
        ];
    }
}
