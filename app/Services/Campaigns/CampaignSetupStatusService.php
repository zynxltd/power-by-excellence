<?php

namespace App\Services\Campaigns;

use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\DistributionConfig;

class CampaignSetupStatusService
{
    /**
     * @return list<array{key: string, label: string, complete: bool, route: ?string}>
     */
    public function checklist(Campaign $campaign): array
    {
        $campaign->loadMissing(['deliveries.buyer', 'account']);

        $activeBuyer = $campaign->deliveries
            ->contains(fn ($delivery) => ($delivery->buyer?->status ?? 'inactive') === 'active');

        $activeDelivery = $campaign->deliveries
            ->contains(fn ($delivery) => ($delivery->status ?? 'inactive') === 'active');

        $apiKeyExists = ApiKey::query()
            ->where('account_id', $campaign->account_id)
            ->where('is_active', true)
            ->exists();

        $pingTreeConfigured = ! $campaign->use_advanced_distribution
            || DistributionConfig::query()
                ->where('campaign_id', $campaign->id)
                ->where('is_active', true)
                ->exists();

        $leadsToday = $campaign->leads()->whereDate('received_at', today())->count();

        return [
            [
                'key' => 'active_buyer',
                'label' => 'At least one active buyer linked',
                'complete' => $activeBuyer,
                'route' => route('campaigns.show', $campaign),
            ],
            [
                'key' => 'active_delivery',
                'label' => 'At least one active delivery configured',
                'complete' => $activeDelivery,
                'route' => route('campaigns.show', $campaign),
            ],
            [
                'key' => 'api_key',
                'label' => 'Active API key for lead ingest',
                'complete' => $apiKeyExists,
                'route' => route('api-keys.index'),
            ],
            [
                'key' => 'ping_tree',
                'label' => 'Ping tree configured (advanced distribution)',
                'complete' => $pingTreeConfigured,
                'route' => route('distribution.index', ['campaign_id' => $campaign->id]),
            ],
            [
                'key' => 'leads_today',
                'label' => 'Leads received today',
                'complete' => $leadsToday > 0,
                'route' => route('leads.index', ['campaign_id' => $campaign->id]),
            ],
            [
                'key' => 'test_lead',
                'label' => 'Send a test lead via API spec',
                'complete' => $leadsToday > 0,
                'route' => route('campaigns.api-spec', $campaign),
            ],
        ];
    }
}
