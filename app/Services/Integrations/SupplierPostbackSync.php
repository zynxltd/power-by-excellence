<?php

namespace App\Services\Integrations;

use App\Models\Postback;
use App\Models\Supplier;

class SupplierPostbackSync
{
    public const SYNC_KEY = 'supplier_default_postback';

    /**
     * @return list<string>
     */
    public static function defaultEvents(): array
    {
        return [
            'lead.sold',
            'lead.accepted',
            'lead.rejected',
            'lead.unsold',
        ];
    }

    public function sync(Supplier $supplier, ?string $url): void
    {
        $existing = Postback::withoutGlobalScopes()
            ->where('account_id', $supplier->account_id)
            ->where('supplier_id', $supplier->id)
            ->get()
            ->first(fn (Postback $postback) => ($postback->config['synced_from'] ?? null) === self::SYNC_KEY);

        if (blank($url)) {
            $existing?->delete();

            return;
        }

        $payload = [
            'account_id' => $supplier->account_id,
            'supplier_id' => $supplier->id,
            'campaign_id' => null,
            'name' => "Supplier default - {$supplier->name}",
            'url' => $url,
            'method' => 'get',
            'events' => self::defaultEvents(),
            'is_active' => true,
            'config' => ['synced_from' => self::SYNC_KEY],
        ];

        if ($existing) {
            $existing->update($payload);

            return;
        }

        Postback::create($payload);
    }
}
