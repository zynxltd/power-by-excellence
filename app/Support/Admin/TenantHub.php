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
                    ['label' => 'API documentation', 'href' => route('api-docs.index', $campaignId ? ['campaign_id' => $campaignId] : []), 'description' => 'Lead ingest reference and examples'],
                    $campaignId ? ['label' => 'API spec editor', 'href' => route('campaigns.api-spec', $campaignId), 'description' => 'Edit campaign field schema'] : null,
                    $campaignId ? ['label' => 'Ping tree', 'href' => route('distribution.create').$campaignQuery, 'description' => 'Distribution routing'] : null,
                    $campaignId ? ['label' => 'Add delivery', 'href' => route('deliveries.create').'?campaign_id='.$campaignId, 'description' => 'New buyer delivery'] : null,
                    ['label' => 'Lead pipeline', 'href' => route('leads.index', $campaignId ? ['campaign_id' => $campaignId] : []), 'description' => 'Search and inspect leads'],
                    ['label' => 'Hosted forms', 'href' => route('forms.index'), 'description' => 'Form builder'],
                ])),
            ],
            [
                'title' => 'Operations',
                'links' => array_values(array_filter([
                    ['label' => 'Live operations', 'href' => route('operations.index'), 'description' => 'Real-time queue and deliveries'],
                    ['label' => 'Routing simulator', 'href' => route('routing.simulator'), 'description' => 'Test tier routing'],
                    ['label' => 'Deliveries', 'href' => route('deliveries.index', $campaignId ? ['campaign_id' => $campaignId] : []), 'description' => 'Buyer delivery configs'],
                    ['label' => 'Delivery logs', 'href' => route('logs.delivery'), 'description' => 'Post attempt history'],
                    ['label' => 'API logs', 'href' => route('logs.api'), 'description' => 'Ingest API requests'],
                    ['label' => 'Access logs', 'href' => route('logs.access'), 'description' => 'Admin sign-ins'],
                    ['label' => 'Change logs', 'href' => route('logs.changes'), 'description' => 'Config audit trail'],
                    ['label' => 'Security logs', 'href' => route('logs.security'), 'description' => 'Security events'],
                    ['label' => 'Quarantine', 'href' => route('quarantine.index'), 'description' => 'Held leads'],
                    (auth()->user()?->isSuperAdmin() && \App\Support\Tenancy\TenantResolver::isCentralHost())
                        ? ['label' => 'Live feed', 'href' => route('live-feed.index'), 'description' => 'Platform event stream']
                        : null,
                    (auth()->user()?->isSuperAdmin() && \App\Support\Tenancy\TenantResolver::isCentralHost())
                        ? ['label' => 'Notifications', 'href' => route('notifications.admin.index'), 'description' => 'Alert configuration']
                        : null,
                ])),
            ],
            [
                'title' => 'Finance & tools',
                'links' => array_values(array_filter([
                    ['label' => 'Finance', 'href' => route('finance.index'), 'description' => 'Revenue and margin'],
                    ['label' => 'Billing', 'href' => route('billing.index'), 'description' => 'Buyer credits'],
                    ['label' => 'Reports', 'href' => route('reports.index'), 'description' => 'Performance analytics'],
                    ['label' => 'API keys', 'href' => route('api-keys.index'), 'description' => 'Ingest authentication'],
                    ['label' => 'Integrations', 'href' => route('integrations.index'), 'description' => 'Webhooks, lead sources, fraud detection'],
                    ['label' => 'Webhooks', 'href' => route('webhooks.index'), 'description' => 'Outbound events'],
                    ['label' => 'Postbacks', 'href' => route('postbacks.index'), 'description' => 'Conversion postbacks'],
                    ['label' => 'Import data', 'href' => route('imports.index'), 'description' => 'CSV bulk import'],
                    ['label' => 'Features', 'href' => route('features.index'), 'description' => 'Feature flags'],
                    ['label' => 'Settings', 'href' => route('settings.edit'), 'description' => 'Platform settings'],
                    ['label' => 'Branding', 'href' => route('branding.edit'), 'description' => 'Logo and colours'],
                    ['label' => 'Support', 'href' => (auth()->user()?->isSuperAdmin() && \App\Support\Tenancy\TenantResolver::isCentralHost()) ? route('support.admin.index') : route('support.index'), 'description' => (auth()->user()?->isSuperAdmin() && \App\Support\Tenancy\TenantResolver::isCentralHost()) ? 'Respond to tenant tickets' : 'Contact support'],
                    ['label' => 'Help centre', 'href' => route('help.index'), 'description' => 'Documentation'],
                    ['label' => 'Profile', 'href' => route('profile.edit'), 'description' => 'Your account'],
                    auth()->user()?->isSuperAdmin()
                        ? ['label' => 'Horizon (queues)', 'href' => url('/horizon'), 'description' => 'Queue monitor']
                        : null,
                    auth()->user()?->isSuperAdmin()
                        ? ['label' => 'Telescope (debug)', 'href' => url('/telescope'), 'description' => 'Request debugger']
                        : null,
                ])),
            ],
        ];
    }
}
