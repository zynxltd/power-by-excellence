<?php

namespace App\Services\ClickTrack;

use App\Models\TrackingConversion;
use App\Models\TrackingLink;
use Illuminate\Support\Collection;

class ClickTrackPendingQueueService
{
    public function __construct(protected ClickCapService $caps) {}

    /**
     * @return array{count: int, items: list<array<string, mixed>>}
     */
    public function conversionQueue(?int $accountId, int $limit = 10): array
    {
        $items = TrackingConversion::query()
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->where('status', TrackingConversion::STATUS_PENDING)
            ->with(['trackingLink:id,name', 'supplier:id,name', 'campaign:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (TrackingConversion $c) => [
                'id' => $c->id,
                'goal' => $c->goal,
                'payout' => $c->payout,
                'revenue' => $c->revenue,
                'tracking_link' => $c->trackingLink?->only(['id', 'name']),
                'supplier' => $c->supplier?->only(['id', 'name']),
                'campaign' => $c->campaign?->only(['id', 'name']),
            ])->all();

        $count = TrackingConversion::query()
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->where('status', TrackingConversion::STATUS_PENDING)
            ->count();

        return ['count' => $count, 'items' => $items];
    }

    /** @return list<array<string, mixed>> */
    public function capAlerts(?int $accountId, int $limit = 10): array
    {
        $links = TrackingLink::query()
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->where('status', 'active')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return $links->map(fn (TrackingLink $link) => $this->caps->usageForLink($link))
            ->filter(fn (array $u) => $u['click_soft_cap_reached'] || $u['click_cap_reached'] || $u['conversion_soft_cap_reached'] || $u['conversion_cap_reached'])
            ->take($limit)->values()->all();
    }

    /** @param Collection<int, TrackingLink> $links */
    public function capUsageForLinks(Collection $links): array
    {
        return $links->map(fn (TrackingLink $link) => $this->caps->usageForLink($link))->all();
    }
}
