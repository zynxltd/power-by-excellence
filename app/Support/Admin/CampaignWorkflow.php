<?php

namespace App\Support\Admin;

use App\Models\Campaign;

class CampaignWorkflow
{
    /**
     * @return array{
     *     campaign: array{id: int, name: string, reference: string},
     *     distributionConfigId: int|null,
     *     tenantHub: array<string, mixed>|null
     * }|null
     */
    public static function forCampaign(?Campaign $campaign, ?int $distributionConfigId = null): ?array
    {
        if (! $campaign) {
            return null;
        }

        $campaign->loadMissing('account');

        $configId = $distributionConfigId;
        if ($configId === null) {
            $active = $campaign->distributionConfigs()->where('is_active', true)->first()
                ?? $campaign->distributionConfigs()->orderByDesc('updated_at')->first();
            $configId = $active?->id;
        }

        return [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'reference' => $campaign->reference,
            ],
            'distributionConfigId' => $configId,
            'tenantHub' => TenantHub::forAccount($campaign->account, $campaign->id),
        ];
    }

    /**
     * @return array{
     *     campaign: array{id: int, name: string, reference: string},
     *     distributionConfigId: int|null,
     *     tenantHub: array<string, mixed>|null
     * }|null
     */
    public static function fromId(?int $campaignId, ?int $distributionConfigId = null): ?array
    {
        if (! $campaignId) {
            return null;
        }

        return self::forCampaign(Campaign::query()->find($campaignId), $distributionConfigId);
    }
}
