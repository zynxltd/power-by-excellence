<?php

namespace App\Services\ClickTrack;

use App\Models\Supplier;
use App\Models\SupplierClickPayout;
use App\Models\TrackingClick;
use App\Models\TrackingConversion;
use App\Models\TrackingLink;

class SupplierClickStatsService
{
    public function __construct(
        protected ClickCapService $caps,
        protected ClickTrackPendingQueueService $pendingQueue,
        protected SupplierClickPayoutService $payouts,
    ) {}

    /** @return array<string, mixed> */
    public function forSupplier(Supplier $supplier): array
    {
        $links = TrackingLink::query()
            ->where('supplier_id', $supplier->id)
            ->withCount(['clicks', 'conversions'])
            ->orderByDesc('updated_at')
            ->get();

        $linkCaps = $this->pendingQueue->capUsageForLinks($links);

        $pendingEarnings = (float) SupplierClickPayout::query()
            ->where('supplier_id', $supplier->id)
            ->where('status', SupplierClickPayout::STATUS_PENDING)
            ->sum('amount');

        $approvedEarnings = (float) SupplierClickPayout::query()
            ->where('supplier_id', $supplier->id)
            ->where('status', SupplierClickPayout::STATUS_APPROVED)
            ->sum('amount');

        return [
            'links' => $links->map(fn (TrackingLink $link) => [
                ...$link->toArray(),
                'revenue_share_pct' => $link->config['revenue_share_pct'] ?? null,
                'payout_amount' => $link->payout_amount,
            ]),
            'link_caps' => $linkCaps,
            'clicks_today' => TrackingClick::query()->where('supplier_id', $supplier->id)->where('clicked_at', '>=', today())->count(),
            'conversions_7d' => TrackingConversion::query()->where('supplier_id', $supplier->id)->where('status', TrackingConversion::STATUS_APPROVED)->where('created_at', '>=', today()->subDays(6))->count(),
            'pending_conversions' => TrackingConversion::query()->where('supplier_id', $supplier->id)->where('status', TrackingConversion::STATUS_PENDING)->count(),
            'pending_earnings' => round($pendingEarnings, 2),
            'approved_earnings' => round($approvedEarnings, 2),
            'cap_alerts' => collect($linkCaps)->filter(fn (array $u) => $u['click_soft_cap_reached'] || $u['click_cap_reached'] || $u['conversion_soft_cap_reached'] || $u['conversion_cap_reached'])->values()->all(),
            'recent_clicks' => TrackingClick::query()->where('supplier_id', $supplier->id)->with('trackingLink:id,name')->orderByDesc('clicked_at')->limit(20)->get(),
            'recent_payouts' => SupplierClickPayout::query()
                ->where('supplier_id', $supplier->id)
                ->with('trackingConversion.trackingLink:id,name')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
        ];
    }
}
