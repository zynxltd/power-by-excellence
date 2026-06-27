<?php

namespace App\Services\Portal;

use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Postback;
use App\Models\Supplier;
use App\Services\Api\CampaignApiSpecService;
use App\Services\Postbacks\SupplierPostbackService;
use App\Services\Webhooks\BuyerWebhookService;
use App\Support\Tenancy\TenantResolver;

class PortalIntegrationsService
{
    public function __construct(
        protected CampaignApiSpecService $specService,
        protected BuyerWebhookService $buyerWebhooks,
        protected SupplierPostbackService $supplierPostbacks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forBuyer(Buyer $buyer): array
    {
        $account = $buyer->account;
        $apiBase = TenantResolver::apiBaseUrl($account);
        $webhookRequests = $this->buyerWebhooks->requestsForBuyer($buyer);
        $liveWebhooks = $this->buyerWebhooks->liveForBuyer($buyer);

        return [
            'apiBaseUrl' => $apiBase,
            'tenantHost' => TenantResolver::portalHost($account),
            'currency' => $buyer->resolvedCurrency(),
            'partner' => $buyer->only(['reference', 'name', 'id']),
            'webhooks' => $liveWebhooks,
            'webhookRequests' => $webhookRequests,
            'webhookEventOptions' => BuyerWebhookService::eventOptions(),
            'webhookStats' => [
                'live' => count($liveWebhooks),
                'pending' => collect($webhookRequests)->where('approval_status', BuyerWebhookService::STATUS_PENDING)->count(),
                'draft' => collect($webhookRequests)->where('approval_status', BuyerWebhookService::STATUS_DRAFT)->count(),
            ],
            'helpUrls' => $this->buyerHelpUrls(),
            'guides' => $this->buyerGuides(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forSupplier(Supplier $supplier, ?int $campaignId = null): array
    {
        $account = $supplier->account;
        $apiBase = TenantResolver::apiBaseUrl($account);

        $campaigns = Campaign::query()
            ->where('account_id', $account->id)
            ->whereHas('campaignSuppliers', fn ($q) => $q->where('supplier_id', $supplier->id))
            ->orderBy('name')
            ->get(['id', 'name', 'reference']);

        $selectedCampaign = $campaignId
            ? $campaigns->firstWhere('id', $campaignId)
            : $campaigns->first();
        $selectedSpec = $selectedCampaign ? $this->specService->defaultSpec($selectedCampaign) : null;

        $apiKeys = \App\Models\ApiKey::query()
            ->where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get(['name', 'key_prefix', 'permissions', 'last_used_at']);

        $postbacks = Postback::query()
            ->where('account_id', $account->id)
            ->where(function ($query) use ($supplier) {
                $query->where('supplier_id', $supplier->id)->orWhereNull('supplier_id');
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['name', 'method', 'events', 'supplier_id']);

        $postbackRequests = $this->supplierPostbacks->requestsForSupplier($supplier);
        $livePostbacks = $postbacks->map(fn (Postback $postback) => [
            'name' => $postback->name,
            'method' => strtoupper($postback->method),
            'events' => $postback->events ?? [],
            'scoped_to_you' => $postback->supplier_id === $supplier->id,
        ])->values()->all();

        $settings = $supplier->affiliate_settings ?? [];

        return [
            'apiBaseUrl' => $apiBase,
            'tenantHost' => TenantResolver::portalHost($account),
            'currency' => $account->default_currency ?? 'GBP',
            'partner' => $supplier->only(['reference', 'name', 'id']),
            'sources' => $supplier->sources()->orderBy('sid')->get(['id', 'sid', 'name']),
            'campaigns' => $campaigns->map(fn (Campaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'reference' => $c->reference,
            ])->values()->all(),
            'selectedCampaign' => $selectedCampaign?->only(['id', 'name', 'reference']),
            'selectedSpec' => $selectedSpec,
            'apiKeys' => $apiKeys->map(fn (\App\Models\ApiKey $key) => [
                'name' => $key->name,
                'prefix' => $key->key_prefix,
                'permissions' => $key->permissions ?? [],
                'last_used_at' => $key->last_used_at?->toDateTimeString(),
            ])->values()->all(),
            'postbacks' => $livePostbacks,
            'postbackRequests' => $postbackRequests,
            'postbackEventOptions' => SupplierPostbackService::eventOptions(),
            'postbackStats' => [
                'live' => count($livePostbacks),
                'pending' => collect($postbackRequests)->where('approval_status', SupplierPostbackService::STATUS_PENDING)->count(),
                'draft' => collect($postbackRequests)->where('approval_status', SupplierPostbackService::STATUS_DRAFT)->count(),
            ],
            'defaultPostbackUrlExample' => url('/api/mock/postback?lead_uuid=[lead_uuid]&payout=[payout]&sid=[sid]'),
            'defaultPostbackUrl' => $settings['default_postback_url'] ?? null,
            'helpUrls' => $this->supplierHelpUrls(),
            'guides' => $this->supplierGuides(),
        ];
    }

    /**
     * @return list<array{label: string, description: string, href: string}>
     */
    protected function supplierHelpUrls(): array
    {
        return [
            [
                'label' => 'Lead ingest API',
                'description' => 'REST endpoints, authentication, field schemas, and polling examples.',
                'href' => route('help.show', 'supplier-portal-leads'),
            ],
            [
                'label' => 'SID & postback tracking',
                'description' => 'How SIDs flow from ingest to reporting and conversion postbacks.',
                'href' => route('help.show', 'supplier-tracking-sids'),
            ],
            [
                'label' => 'CSV export guide',
                'description' => 'Download submitted leads, date filters, and reconciliation tips.',
                'href' => route('help.show', 'supplier-portal-csv-export'),
            ],
            [
                'label' => 'Supplier portal overview',
                'description' => 'Dashboard, leads, form embeds, and day-to-day portal workflows.',
                'href' => route('help.show', 'supplier-portal-overview'),
            ],
        ];
    }

    protected function buyerHelpUrls(): array
    {
        return [
            [
                'label' => 'Feedback & returns API',
                'description' => 'REST endpoints, authentication, and payload examples for conversion reporting.',
                'href' => route('help.show', 'buyer-portal-feedback-returns'),
            ],
            [
                'label' => 'Webhooks & outbound events',
                'description' => 'Sample payloads, event types, and approval workflow for lead.sold notifications.',
                'href' => route('help.show', 'buyer-portal-webhooks'),
            ],
            [
                'label' => 'CSV export guide',
                'description' => 'Download purchased leads, date filters, and CRM import tips.',
                'href' => route('help.show', 'buyer-portal-csv-export'),
            ],
            [
                'label' => 'Buyer portal overview',
                'description' => 'How leads arrive, billing, and day-to-day portal workflows.',
                'href' => route('help.show', 'buyer-portal-overview'),
            ],
        ];
    }

    /**
     * @return list<array{title: string, body: string}>
     */
    protected function buyerGuides(): array
    {
        return [
            [
                'title' => 'Pull vs push data',
                'body' => 'Use the portal CSV export or scheduled exports to pull historical inventory. For real-time updates when leads are sold to you, configure webhooks below or ask your administrator to set up delivery endpoints.',
            ],
            [
                'title' => 'API keys',
                'body' => 'REST endpoints require a tenant API key with buyers.manage permission. Keys are created by your platform administrator — they are not shown in this portal for security.',
            ],
            [
                'title' => 'Authentication',
                'body' => 'Send Authorization: Bearer {prefix}|{secret} or X-API-Key: {prefix}|{secret}. Always call the tenant API host shown above, not the central marketing domain.',
            ],
            [
                'title' => 'Lead delivery',
                'body' => 'New leads are delivered to your ping/post URLs configured on the platform (see Deliveries in admin). The REST API is for feedback and exports — not for receiving live lead posts.',
            ],
        ];
    }

    /**
     * @return list<array{title: string, body: string}>
     */
    protected function supplierGuides(): array
    {
        return [
            [
                'title' => 'SID tracking',
                'body' => 'Pass sid (and optional ssid) on every POST /leads request. Values must match your configured sources so reporting, CSV exports, and postbacks reconcile correctly.',
            ],
            [
                'title' => 'Test mode',
                'body' => 'Include "test": true to validate payloads without pinging buyers or firing postbacks. Omit for live traffic.',
            ],
            [
                'title' => 'API keys',
                'body' => 'Supplier keys are scoped to your account and typically include leads.create and leads.read. Contact your administrator to rotate or provision keys.',
            ],
            [
                'title' => 'Form embeds',
                'body' => 'Hosted forms submit through the platform with your supplier_id and sid pre-filled. Use Form embeds in the portal for iframe and direct-link codes.',
            ],
        ];
    }
}
