<?php

namespace App\Services\ClickTrack;

use App\Models\Supplier;
use App\Models\TrackingClick;
use App\Models\TrackingConversion;
use App\Models\TrackingLink;

class SupplierClickStatsService
{
    public function __construct(
        protected ClickCapService $caps,
        protected ClickTrackPendingQueueService $pendingQueue,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forSupplier(Supplier $supplier): array
    {
        $links = TrackingLink::query()
            ->where('supplier_id', $supplier->id)
            ->withCount(['clicks', 'conversions'])
            ->orderByDesc('updated_at')
            ->get();

        $clicksToday = TrackingClick::query()
            ->where('supplier_id', $supplier->id)
            ->where('clicked_at', '>=', today())
            ->count();

        $conversionsApproved = TrackingConversion::query()
            ->where('supplier_id', $supplier->id)
            ->where('status', TrackingConversion::STATUS_APPROVED)
            ->where('created_at', '>=', today()->subDays(6))
            ->count();

        $pendingCount = TrackingConversion::query()
            ->where('supplier_id', $supplier->id)
            ->where('status', TrackingConversion::STATUS_PENDING)
            ->count();

        $linkCaps = $this->pendingQueue->capUsageForLinks($links);

        return [
            'links' => $links,
            'link_caps' => $linkCaps,
            'clicks_today' => $clicksToday,
            'conversions_7d' => $conversionsApproved,
            'pending_conversions' => $pendingCount,
            'cap_alerts' => collect($linkCaps)->filter(fn (array $u) => $u['click_cap_reached'] || $u['conversion_cap_reached'])->values()->all(),
            'recent_clicks' => TrackingClick::query()
                ->where('supplier_id', $supplier->id)
                ->with('trackingLink:id,name')
                ->orderByDesc('clicked_at')
                ->limit(20)
                ->get(),
        ];
    }
}
