<?php

namespace App\Services\ClickTrack;

use App\Models\Lead;
use App\Models\TrackingClick;
use App\Models\TrackingConversion;
use App\Models\TrackingLink;
use App\Services\Logging\PlatformLogger;
use App\Services\Postbacks\PostbackDispatcher;
use Illuminate\Support\Str;

class ConversionTrackingService
{
    public function __construct(
        protected PostbackDispatcher $postbacks,
        protected ClickCapService $caps,
        protected SupplierClickPayoutService $payouts,
    ) {}

    public function fromLeadSold(Lead $lead): ?TrackingConversion
    {
        $lead->loadMissing(['financials', 'campaign', 'account']);

        $existing = TrackingConversion::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->first();

        if ($existing) {
            return $this->approveIfAuto($existing, $lead);
        }

        $click = $lead->tracking_click_id
            ? TrackingClick::withoutGlobalScopes()->with('trackingLink')->find($lead->tracking_click_id)
            : null;
        $link = $click?->trackingLink;

        if (! $click && ! $lead->supplier_id) {
            return null;
        }

        $payout = (float) ($link?->payout_amount ?? $lead->campaign?->payout_amount ?? $lead->financials?->payout ?? 0);
        $revenue = (float) ($link?->revenue_amount ?? $lead->financials?->revenue ?? 0);
        $goal = $link?->goal ?? 'lead';
        $autoApprove = (bool) ($link?->config['auto_approve_conversions'] ?? true);

        $conversion = TrackingConversion::create([
            'account_id' => $lead->account_id,
            'tracking_link_id' => $link?->id,
            'tracking_click_id' => $click?->id,
            'lead_id' => $lead->id,
            'campaign_id' => $lead->campaign_id,
            'supplier_id' => $lead->supplier_id ?? $click?->supplier_id,
            'buyer_id' => $lead->sold_to_buyer_id,
            'conversion_uuid' => (string) Str::uuid(),
            'goal' => $goal,
            'status' => $autoApprove ? TrackingConversion::STATUS_APPROVED : TrackingConversion::STATUS_PENDING,
            'payout' => $payout,
            'revenue' => $revenue,
            'sale_amount' => $revenue,
            'approved_at' => $autoApprove ? now() : null,
        ]);

        if ($autoApprove) {
            $this->fireApprovedPostback($conversion, $lead);
        }

        $this->payouts->syncFromConversion($conversion);

        PlatformLogger::leadEvent($lead, 'conversion.recorded', 'Click Track conversion recorded', [
            'conversion_uuid' => $conversion->conversion_uuid,
            'status' => $conversion->status,
        ]);

        return $conversion;
    }

    public function fromBuyerFeedback(Lead $lead, string $event): void
    {
        $conversion = TrackingConversion::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->first();

        if (! $conversion) {
            return;
        }

        if (in_array($event, ['lead.converted', 'lead.funded'], true)) {
            $this->approve($conversion, $lead, 'Buyer feedback: '.$event);
        } elseif ($event === 'lead.returned') {
            $this->reject($conversion, $lead, 'Buyer returned lead');
        }
    }

    public function approve(TrackingConversion $conversion, ?Lead $lead = null, ?string $reason = null): TrackingConversion
    {
        if ($conversion->status === TrackingConversion::STATUS_APPROVED) {
            return $conversion;
        }

        $conversion->update([
            'status' => TrackingConversion::STATUS_APPROVED,
            'approved_at' => now(),
            'rejected_at' => null,
            'rejected_reason' => null,
        ]);

        $lead ??= $conversion->lead;
        if ($lead) {
            $this->fireApprovedPostback($conversion->fresh(), $lead);
        }

        $this->payouts->markApproved($conversion->fresh());

        return $conversion->fresh();
    }

    public function reject(TrackingConversion $conversion, ?Lead $lead = null, ?string $reason = null): TrackingConversion
    {
        $conversion->update([
            'status' => TrackingConversion::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejected_reason' => $reason,
        ]);

        $lead ??= $conversion->lead;
        if ($lead) {
            $this->postbacks->dispatch($lead, 'conversion.rejected');
        }

        return $conversion->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordInbound(TrackingLink $link, array $data): TrackingConversion
    {
        if ($this->caps->conversionCapReached($link)) {
            abort(429, 'Conversion cap reached for this offer.');
        }

        $click = null;
        if (! empty($data['click_id'])) {
            $click = $link->clicks()->withoutGlobalScopes()
                ->where('click_uuid', $data['click_id'])
                ->first();
        }

        $status = match (strtolower((string) ($data['status'] ?? 'approved'))) {
            'pending' => TrackingConversion::STATUS_PENDING,
            'rejected' => TrackingConversion::STATUS_REJECTED,
            default => TrackingConversion::STATUS_APPROVED,
        };

        $conversion = TrackingConversion::create([
            'account_id' => $link->account_id,
            'tracking_link_id' => $link->id,
            'tracking_click_id' => $click?->id,
            'lead_id' => $click?->lead_id,
            'campaign_id' => $link->campaign_id,
            'supplier_id' => $link->supplier_id ?? $click?->supplier_id,
            'buyer_id' => $link->buyer_id,
            'conversion_uuid' => (string) Str::uuid(),
            'goal' => $data['goal'] ?? $link->goal ?? 'sale',
            'status' => $status,
            'payout' => (float) ($data['payout'] ?? $link->payout_amount ?? 0),
            'revenue' => (float) ($data['revenue'] ?? $link->revenue_amount ?? 0),
            'sale_amount' => (float) ($data['sale_amount'] ?? $data['revenue'] ?? 0),
            'external_id' => $data['external_id'] ?? null,
            'approved_at' => $status === TrackingConversion::STATUS_APPROVED ? now() : null,
            'rejected_at' => $status === TrackingConversion::STATUS_REJECTED ? now() : null,
            'rejected_reason' => $data['rejected_reason'] ?? null,
        ]);

        if ($status === TrackingConversion::STATUS_APPROVED && $click?->lead) {
            $this->fireApprovedPostback($conversion, $click->lead);
        }

        $this->payouts->syncFromConversion($conversion);

        return $conversion;
    }

    protected function approveIfAuto(TrackingConversion $conversion, Lead $lead): TrackingConversion
    {
        $autoApprove = (bool) ($conversion->trackingLink?->config['auto_approve_conversions'] ?? true);

        if ($autoApprove && $conversion->status === TrackingConversion::STATUS_PENDING) {
            return $this->approve($conversion, $lead, 'Lead sold');
        }

        return $conversion;
    }

    protected function fireApprovedPostback(TrackingConversion $conversion, Lead $lead): void
    {
        $metadata = $lead->metadata ?? [];
        $metadata['conversion_uuid'] = $conversion->conversion_uuid;
        $metadata['conversion_status'] = $conversion->status;
        $lead->update(['metadata' => $metadata]);

        $this->postbacks->dispatch($lead->fresh(), 'conversion.approved');
    }
}
