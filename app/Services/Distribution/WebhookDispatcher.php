<?php

namespace App\Services\Distribution;

use App\Models\Account;
use App\Models\Lead;
use App\Models\Webhook;
use App\Services\Logging\PlatformLogger;
use Illuminate\Support\Facades\Http;
use Throwable;

class WebhookDispatcher
{
    public function dispatch(Account $account, string $event, Lead $lead): void
    {
        $lead->loadMissing(['financials', 'soldToBuyer:id,name,reference']);

        $webhooks = Webhook::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->live()
            ->get()
            ->filter(fn (Webhook $w) => in_array($event, $w->events ?? [], true))
            ->filter(fn (Webhook $w) => $this->matchesBuyerScope($w, $lead, $event));

        foreach ($webhooks as $webhook) {
            try {
                Http::timeout(5)->post($webhook->url, $this->payload($event, $lead));
            } catch (Throwable $e) {
                PlatformLogger::error('Webhook dispatch failed', [
                    'webhook_id' => $webhook->id,
                    'event' => $event,
                ], $lead, $e);
            }
        }
    }

    protected function matchesBuyerScope(Webhook $webhook, Lead $lead, string $event): bool
    {
        if (! $webhook->buyer_id) {
            return true;
        }

        if ($event === 'lead.sold') {
            return (int) $lead->sold_to_buyer_id === (int) $webhook->buyer_id;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(string $event, Lead $lead): array
    {
        return [
            'event' => $event,
            'lead_id' => $lead->id,
            'lead_uuid' => $lead->uuid,
            'campaign_id' => $lead->campaign_id,
            'buyer_id' => $lead->sold_to_buyer_id,
            'buyer_name' => $lead->soldToBuyer?->name,
            'buyer_reference' => $lead->soldToBuyer?->reference,
            'status' => $lead->status->value,
            'revenue' => $lead->financials?->revenue,
            'payout' => $lead->financials?->payout,
            'field_data' => $lead->field_data,
            'received_at' => $lead->received_at?->toIso8601String(),
            'distributed_at' => $lead->distributed_at?->toIso8601String(),
        ];
    }
}
