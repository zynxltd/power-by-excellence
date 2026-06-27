<?php

namespace App\Support\Admin;

use App\Models\Account;
use App\Support\Tenancy\AccountContext;
use App\Support\Tenancy\TenantResolver;

class TenantHub
{
    /**
     * Super-admin shortcuts when on central host (no tenant selected).
     *
     * @return array<string, mixed>
     */
    public static function forCentralAdmin(): array
    {
        return [
            'name' => 'Central admin',
            'is_central' => true,
            'sections' => [
                [
                    'title' => 'Overview',
                    'links' => [
                        ['label' => 'Dashboard', 'href' => route('dashboard'), 'description' => 'Platform overview and partner list'],
                        ['label' => 'Command Center', 'href' => route('command-center.index'), 'description' => 'Cross-tenant health and alerts'],
                        ['label' => 'System status', 'href' => route('status.index'), 'description' => 'Public status page preview'],
                    ],
                ],
                [
                    'title' => 'Platforms',
                    'links' => [
                        ['label' => 'Partner platforms', 'href' => route('accounts.index'), 'description' => 'Switch tenant context or open portals'],
                        ['label' => 'Tenant billing', 'href' => route('accounts.billing.index'), 'description' => 'Subscription and usage billing'],
                        ['label' => 'Notifications', 'href' => route('notifications.admin.index'), 'description' => 'Broadcasts and system alerts'],
                        ['label' => 'Support queue', 'href' => route('support.admin.index'), 'description' => 'Tenant support tickets'],
                    ],
                ],
                [
                    'title' => 'Monitoring',
                    'links' => [
                        ['label' => 'Live feed', 'href' => route('live-feed.index'), 'description' => 'Real-time platform activity'],
                        ['label' => 'Platform events', 'href' => route('platform-events.index'), 'description' => 'Lead and delivery event log'],
                        ['label' => 'Live operations', 'href' => route('operations.index'), 'description' => 'Queue depth and ingest'],
                        ['label' => 'Delivery logs', 'href' => route('logs.delivery'), 'description' => 'Ping/post attempt history'],
                    ],
                ],
                [
                    'title' => 'Tools',
                    'links' => [
                        ['label' => 'Ping trees', 'href' => route('distribution.index'), 'description' => 'Distribution configs'],
                        ['label' => 'Routing simulator', 'href' => route('routing.simulator'), 'description' => 'Dry-run tier routing'],
                        ['label' => 'Reports', 'href' => route('reports.index'), 'description' => 'Volume and unit economics'],
                        ['label' => 'Horizon (queues)', 'href' => url('/horizon'), 'description' => 'Queue monitor'],
                        ['label' => 'Telescope (debug)', 'href' => url('/telescope'), 'description' => 'Request debugger'],
                    ],
                ],
            ],
        ];
    }

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
                    ['label' => 'Partner settings', 'href' => (auth()->user()?->isSuperAdmin() && TenantResolver::isCentralHost()) ? route('accounts.index') : route('settings.edit'), 'description' => (auth()->user()?->isSuperAdmin() && TenantResolver::isCentralHost()) ? 'Switch or view all platforms' : 'Platform configuration'],
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
                    ['label' => 'Buyer feedback', 'href' => route('buyer-feedback.index', $campaignId ? ['campaign_id' => $campaignId] : []), 'description' => 'Invalid flags and conversion reports by supplier'],
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
                    (auth()->user()?->isSuperAdmin() && TenantResolver::isCentralHost())
                        ? ['label' => 'Live feed', 'href' => route('live-feed.index'), 'description' => 'Platform event stream']
                        : null,
                    (auth()->user()?->isSuperAdmin() && TenantResolver::isCentralHost())
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
                    ['label' => 'Support', 'href' => (auth()->user()?->isSuperAdmin() && TenantResolver::isCentralHost()) ? route('support.admin.index') : route('support.index'), 'description' => (auth()->user()?->isSuperAdmin() && TenantResolver::isCentralHost()) ? 'Respond to tenant tickets' : 'Contact support'],
                    ['label' => 'Help centre', 'href' => route('help.index'), 'description' => 'Documentation'],
                    ['label' => 'Profile', 'href' => route('profile.edit'), 'description' => 'Your account'],
                ])),
            ],
        ];
    }
}
