<?php

namespace App\Support\Admin;

use App\Models\Account;
use App\Support\Tenancy\AccountContext;

class TenantHub
{
    /**
     * @return array<string, mixed>|null
     */
    public static function forAccount(?Account $account, ?int $campaignId = null): ?array
    {
        if (! $account) {
            return null;
        }

        $activeId = AccountContext::id();

        return [
            'id' => $account->id,
            'name' => $account->brand_name ?: $account->name,
            'slug' => $account->slug,
            'currency' => $account->default_currency,
            'country' => $account->default_country,
            'is_active' => $activeId === $account->id,
            'campaign_id' => $campaignId,
            'sections' => self::sections($campaignId),
        ];
    }

    /**
     * @return list<array{title: string, links: list<array{label: string, href: string, description?: string}>}>
     */
    protected static function sections(?int $campaignId = null): array
    {
        $campaignQuery = $campaignId ? '?campaign_id='.$campaignId : '';

        return [
            [
                'title' => 'Tenant',
                'links' => [
                    ['label' => 'Buyers', 'href' => route('buyers.index'), 'description' => 'Manage buyer accounts and caps'],
                    ['label' => 'Suppliers', 'href' => route('suppliers.index'), 'description' => 'Manage supplier sources'],
                    ['label' => 'Users', 'href' => route('users.index'), 'description' => 'Team members and roles'],
                    ['label' => 'Partner settings', 'href' => (auth()->user()?->isSuperAdmin() && \App\Support\Tenancy\TenantResolver::isCentralHost()) ? route('accounts.index') : route('settings.edit'), 'description' => (auth()->user()?->isSuperAdmin() && \App\Support\Tenancy\TenantResolver::isCentralHost()) ? 'Switch or view all platforms' : 'Platform configuration'],
                ],
            ],
            [
                'title' => 'Campaigns & leads',
                'links' => array_values(array_filter([
                    ['label' => 'All campaigns', 'href' => route('campaigns.index'), 'description' => 'Campaign list for active tenant'],
                    $campaignId ? ['label' => 'This campaign', 'href' => route('campaigns.show', $campaignId), 'description' => 'Campaign overview'] : null,
                    $campaignId ? ['label' => 'API spec', 'href' => route('campaigns.api-spec', $campaignId), 'description' => 'Ingest schema and docs'] : null,
                    $campaignId ? ['label' => 'Ping tree', 'href' => route('distribution.create').$campaignQuery, 'description' => 'Distribution routing'] : null,
                    ['label' => 'Lead pipeline', 'href' => route('leads.index', $campaignId ? ['campaign_id' => $campaignId] : []), 'description' => 'Search and inspect leads'],
                    ['label' => 'Hosted forms', 'href' => route('forms.index'), 'description' => 'Form builder'],
                ])),
            ],
            [
                'title' => 'Operations',
                'links' => [
                    ['label' => 'Live operations', 'href' => route('operations.index'), 'description' => 'Real-time queue and deliveries'],
                    ['label' => 'Deliveries', 'href' => route('deliveries.index'), 'description' => 'Buyer delivery configs'],
                    ['label' => 'Delivery logs', 'href' => route('logs.delivery'), 'description' => 'Post attempt history'],
                    ['label' => 'Quarantine', 'href' => route('quarantine.index'), 'description' => 'Held leads'],
                ],
            ],
            [
                'title' => 'Finance & tools',
                'links' => [
                    ['label' => 'Billing', 'href' => route('billing.index'), 'description' => 'Usage and invoices'],
                    ['label' => 'Reports', 'href' => route('reports.index'), 'description' => 'Performance analytics'],
                    ['label' => 'API keys', 'href' => route('api-keys.index'), 'description' => 'Ingest authentication'],
                    ['label' => 'Import data', 'href' => route('imports.index'), 'description' => 'CSV bulk import'],
                ],
            ],
        ];
    }
}
