<?php

namespace App\Services\ClickTrack;

use App\Models\Lead;
use App\Models\TrackingClick;
use App\Models\TrackingConversion;
use App\Models\TrackingLink;
use App\Services\Delivery\TagInterpolator;
use App\Services\Logging\PlatformLogger;
use App\Services\Postbacks\PostbackDispatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ConversionTrackingService
{
    /**
     * @var list<string>
     */
    public const POSTBACK_MACROS = [
        'click_id',
        'sub1',
        'sub2',
        'sub3',
        'sub4',
        'sub5',
        'payout',
        'conversion_value',
        'lead_uuid',
    ];

    public function __construct(
        protected PostbackDispatcher $postbacks,
        protected ClickCapService $caps,
        protected SupplierClickPayoutService $payouts,
        protected TagInterpolator $interpolator,
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
        $this->fireLinkConversionPostback($conversion->fresh(['trackingLink.supplier', 'trackingClick']), $lead->fresh());
    }

    public function resolveConversionPostbackUrl(TrackingLink $link): ?string
    {
        if (filled($link->conversion_postback_url)) {
            return $link->conversion_postback_url;
        }

        $link->loadMissing('supplier');

        return $link->supplier?->affiliate_settings['default_postback_url'] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function macroFields(TrackingConversion $conversion, ?TrackingClick $click = null, ?Lead $lead = null): array
    {
        $click ??= $conversion->trackingClick;
        $lead ??= $conversion->lead;

        return [
            'click_id' => (string) ($click?->click_uuid ?? ''),
            'sub1' => (string) ($click?->sub1 ?? ''),
            'sub2' => (string) ($click?->sub2 ?? ''),
            'sub3' => (string) ($click?->sub3 ?? ''),
            'sub4' => (string) ($click?->sub4 ?? ''),
            'sub5' => (string) ($click?->sub5 ?? ''),
            'payout' => (string) $conversion->payout,
            'conversion_value' => (string) ($conversion->sale_amount ?? $conversion->revenue ?? ''),
            'lead_uuid' => (string) ($lead?->uuid ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function sampleMacroFields(): array
    {
        return [
            'click_id' => '550e8400-e29b-41d4-a716-446655440000',
            'sub1' => 'aff123',
            'sub2' => 'campaign-a',
            'sub3' => 'placement-1',
            'sub4' => 'creative-b',
            'sub5' => 'geo-uk',
            'payout' => '12.50',
            'conversion_value' => '45.00',
            'lead_uuid' => '7f3c9a2e-4b1d-4e8a-9c0f-1a2b3c4d5e6f',
        ];
    }

    public function expandPostbackUrl(string $template, array $fields): string
    {
        return $this->interpolator->interpolate($template, $fields);
    }

    public function fireLinkConversionPostback(TrackingConversion $conversion, ?Lead $lead = null): void
    {
        $link = $conversion->trackingLink;
        if (! $link) {
            return;
        }

        $template = $this->resolveConversionPostbackUrl($link);
        if (! $template) {
            return;
        }

        $lead ??= $conversion->lead;
        $fields = $this->macroFields($conversion, $conversion->trackingClick, $lead);
        $url = $this->expandPostbackUrl($template, $fields);
        $method = strtolower((string) (($link->conversion_postback_macros ?? [])['method'] ?? 'get'));

        try {
            if ($method === 'post') {
                Http::timeout(8)->post($url, $fields);
            } else {
                Http::timeout(8)->get($url);
            }
        } catch (Throwable $e) {
            PlatformLogger::error('Tracking link conversion postback failed', [
                'tracking_link_id' => $link->id,
                'conversion_uuid' => $conversion->conversion_uuid,
                'url' => $url,
                'method' => $method,
            ], $lead, $e);
        }
    }
}
