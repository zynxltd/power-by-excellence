<?php

namespace App\Services\ClickTrack;

use App\Models\SupplierClickPayout;
use App\Models\TrackingConversion;
use App\Models\TrackingLink;

class SupplierClickPayoutService
{
    public function syncFromConversion(TrackingConversion $conversion): SupplierClickPayout
    {
        $conversion->loadMissing('trackingLink');
        $amounts = $this->amountsForConversion($conversion);

        return SupplierClickPayout::updateOrCreate(
            ['tracking_conversion_id' => $conversion->id],
            [
                'account_id' => $conversion->account_id,
                'supplier_id' => $conversion->supplier_id,
                'amount' => $amounts['payout'],
                'revenue' => $amounts['revenue'],
                'revenue_share_pct' => $amounts['revenue_share_pct'],
                'status' => $conversion->status === TrackingConversion::STATUS_APPROVED
                    ? SupplierClickPayout::STATUS_APPROVED
                    : SupplierClickPayout::STATUS_PENDING,
                'approved_at' => $conversion->status === TrackingConversion::STATUS_APPROVED
                    ? ($conversion->approved_at ?? now())
                    : null,
            ],
        );
    }

    public function markApproved(TrackingConversion $conversion): ?SupplierClickPayout
    {
        $entry = $this->syncFromConversion($conversion->fresh());

        if ($entry->status !== SupplierClickPayout::STATUS_APPROVED) {
            $entry->update([
                'status' => SupplierClickPayout::STATUS_APPROVED,
                'approved_at' => now(),
            ]);
        }

        return $entry->fresh();
    }

    /**
     * @return array{payout: float, revenue: float, revenue_share_pct: float|null}
     */
    public function amountsForConversion(TrackingConversion $conversion): array
    {
        /** @var TrackingLink|null $link */
        $link = $conversion->trackingLink;
        $revenue = (float) ($conversion->revenue ?? $link?->revenue_amount ?? 0);
        $sharePct = isset($link?->config['revenue_share_pct'])
            ? (float) $link->config['revenue_share_pct']
            : null;

        $payout = (float) ($conversion->payout ?? 0);
        if ($payout <= 0 && $sharePct !== null && $revenue > 0) {
            $payout = round($revenue * ($sharePct / 100), 4);
        } elseif ($payout <= 0 && $link?->payout_amount) {
            $payout = (float) $link->payout_amount;
        }

        return [
            'payout' => $payout,
            'revenue' => $revenue,
            'revenue_share_pct' => $sharePct,
        ];
    }
}
