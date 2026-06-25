<?php

namespace App\Support\Campaign;

use App\Models\Campaign;

class CampaignFieldCatalog
{
    /**
     * @return list<array{name: string, label: string, type?: string}>
     */
    public static function forCampaign(?Campaign $campaign): array
    {
        if (! $campaign) {
            return [];
        }

        $campaign->loadMissing('fields');

        $fields = $campaign->fields->map(fn ($f) => [
            'name' => $f->name,
            'label' => $f->label ?: $f->name,
            'type' => $f->type,
        ])->values()->all();

        $apiFields = collect($campaign->api_spec['fields'] ?? [])
            ->map(fn ($f) => [
                'name' => $f['name'] ?? '',
                'label' => ($f['label'] ?? $f['name'] ?? '').' (API)',
                'type' => $f['type'] ?? null,
            ])
            ->filter(fn ($f) => $f['name'] !== '')
            ->values()
            ->all();

        return collect(array_merge($fields, $apiFields))
            ->unique('name')
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, label: string, type?: string}>
     */
    public static function forCampaignId(?int $campaignId): array
    {
        if (! $campaignId) {
            return [];
        }

        return self::forCampaign(Campaign::query()->find($campaignId));
    }
}
