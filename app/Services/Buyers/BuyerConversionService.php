<?php

namespace App\Services\Buyers;

use App\Models\Buyer;
use App\Models\BuyerFeedback;
use App\Models\Lead;
use App\Services\Logging\PlatformLogger;
use App\Services\Postbacks\PostbackDispatcher;
use Illuminate\Support\Facades\Http;
use Throwable;

class BuyerConversionService
{
    public function __construct(
        protected PostbackDispatcher $postbackDispatcher,
    ) {}

    /**
     * @return array{event: string, status: string}
     */
    public function recordFeedback(Buyer $buyer, Lead $lead, string $status, bool $converted = false, ?string $notes = null): array
    {
        $normalized = strtolower(trim($status));
        $event = $this->mapStatusToEvent($normalized, $converted);

        $metadata = $lead->metadata ?? [];
        $metadata['buyer_feedback'] = [
            'status' => $normalized,
            'converted' => $converted,
            'notes' => $notes,
            'buyer_id' => $buyer->id,
            'recorded_at' => now()->toIso8601String(),
        ];
        $metadata['conversion_status'] = $event;

        $lead->update(['metadata' => $metadata]);

        $feedback = BuyerFeedback::updateOrCreate(
            ['lead_id' => $lead->id, 'buyer_id' => $buyer->id],
            [
                'status' => $normalized,
                'converted' => $converted,
                'notes' => $notes,
            ]
        );

        PlatformLogger::leadEvent($lead, $event, "Buyer feedback: {$normalized}", [
            'buyer_id' => $buyer->id,
            'converted' => $converted,
        ]);

        $this->postbackDispatcher->dispatch($lead->fresh(), $event);
        $this->fireBuyerWebhook($buyer, $lead->fresh(), $event, $normalized, $converted, $notes);

        $lead->loadMissing('account');
        if ($lead->account && app(\App\Services\ClickTrack\ClickTrackEntitlementService::class)->isEntitled($lead->account)) {
            app(\App\Services\ClickTrack\ConversionTrackingService::class)->fromBuyerFeedback($lead->fresh(), $event);
        }

        return [
            'event' => $event,
            'status' => $normalized,
            'feedback_id' => $feedback->id,
            'lead_id' => $lead->id,
        ];
    }

    protected function mapStatusToEvent(string $status, bool $converted): string
    {
        if ($converted || in_array($status, ['converted', 'funded', 'sale', 'closed'], true)) {
            if (in_array($status, ['funded', 'funded_loan', 'loan_funded'], true)) {
                return 'lead.funded';
            }

            return 'lead.converted';
        }

        if (in_array($status, ['contacted', 'called', 'callback', 'spoken', 'contact'], true)) {
            return 'lead.contacted';
        }

        if (in_array($status, ['invalid', 'bad_lead', 'returned'], true)) {
            return 'lead.returned';
        }

        return 'lead.contacted';
    }

    protected function fireBuyerWebhook(
        Buyer $buyer,
        Lead $lead,
        string $event,
        string $status,
        bool $converted,
        ?string $notes,
    ): void {
        $settings = $buyer->settings ?? [];
        $url = $settings['conversion_postback_url'] ?? null;

        if (! $url) {
            return;
        }

        $payload = [
            'event' => $event,
            'status' => $status,
            'converted' => $converted,
            'lead_uuid' => $lead->uuid,
            'campaign_reference' => $lead->campaign?->reference,
            'notes' => $notes,
            'revenue' => $lead->financials?->revenue,
        ];

        try {
            Http::timeout(8)->get($url, $payload);
        } catch (Throwable $e) {
            PlatformLogger::error('Buyer conversion webhook failed', [
                'buyer_id' => $buyer->id,
                'url' => $url,
                'event' => $event,
            ], $lead, $e);
        }
    }
}
