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
}
