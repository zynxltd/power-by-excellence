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
        $webhooks = Webhook::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Webhook $w) => in_array($event, $w->events ?? [], true));

        foreach ($webhooks as $webhook) {
            try {
                Http::timeout(5)->post($webhook->url, [
                    'event' => $event,
                    'lead_id' => $lead->id,
                    'lead_uuid' => $lead->uuid,
                    'campaign_id' => $lead->campaign_id,
                    'status' => $lead->status->value,
                    'field_data' => $lead->field_data,
                    'received_at' => $lead->received_at?->toIso8601String(),
                ]);
            } catch (Throwable $e) {
                PlatformLogger::error('Webhook dispatch failed', [
                    'webhook_id' => $webhook->id,
                    'event' => $event,
                ], $lead, $e);
            }
        }
    }
}
