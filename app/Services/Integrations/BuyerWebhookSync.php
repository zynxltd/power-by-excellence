<?php

namespace App\Services\Integrations;

use App\Models\Buyer;
use App\Models\Webhook;

class BuyerWebhookSync
{
    public const SYNC_KEY = 'buyer_sold_webhook';

    public function sync(Buyer $buyer, ?string $url): void
    {
        $existing = Webhook::withoutGlobalScopes()
            ->where('account_id', $buyer->account_id)
            ->where('buyer_id', $buyer->id)
            ->get()
            ->first(fn (Webhook $webhook) => ($webhook->config['synced_from'] ?? null) === self::SYNC_KEY);

        if (blank($url)) {
            $existing?->delete();

            return;
        }

        $payload = [
            'account_id' => $buyer->account_id,
            'buyer_id' => $buyer->id,
            'name' => "Buyer sold - {$buyer->name}",
            'url' => $url,
            'events' => ['lead.sold'],
            'is_active' => true,
            'config' => ['synced_from' => self::SYNC_KEY],
        ];

        if ($existing) {
            $existing->update($payload);

            return;
        }

        Webhook::create($payload);
    }

    public function urlForBuyer(Buyer $buyer): ?string
    {
        $settingsUrl = $buyer->settings['sold_webhook_url'] ?? null;
        if (filled($settingsUrl)) {
            return $settingsUrl;
        }

        $webhook = Webhook::withoutGlobalScopes()
            ->where('account_id', $buyer->account_id)
            ->where('buyer_id', $buyer->id)
            ->get()
            ->first(fn (Webhook $hook) => ($hook->config['synced_from'] ?? null) === self::SYNC_KEY);

        return $webhook?->url;
    }
}
