<?php

namespace App\Services\Portal;

use App\Models\ApiKey;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Postback;
use App\Models\Supplier;
use App\Models\Webhook;
use App\Services\Api\CampaignApiSpecService;
use App\Support\Tenancy\TenantResolver;

class PortalIntegrationsService
{
    public function __construct(
        protected CampaignApiSpecService $specService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forBuyer(Buyer $buyer): array
    {
        $account = $buyer->account;
        $apiBase = TenantResolver::apiBaseUrl($account);

        $webhooks = Webhook::query()
            ->where('account_id', $account->id)
            ->where(function ($query) use ($buyer) {
                $query->where('buyer_id', $buyer->id)->orWhereNull('buyer_id');
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'url', 'events', 'buyer_id']);

        return [
            'apiBaseUrl' => $apiBase,
            'tenantHost' => TenantResolver::portalHost($account),
            'currency' => $buyer->resolvedCurrency(),
            'partner' => $buyer->only(['reference', 'name']),
            'webhooks' => $webhooks->map(fn (Webhook $webhook) => [
                'name' => $webhook->name,
                'events' => $webhook->events ?? [],
                'scoped_to_you' => $webhook->buyer_id === $buyer->id,
                'url_host' => parse_url($webhook->url, PHP_URL_HOST) ?: $webhook->url,
            ])->values()->all(),
            'endpoints' => $this->buyerEndpoints($buyer, $apiBase),
            'guides' => $this->buyerGuides(),
            'samples' => [
                'feedback' => [
                    'lead_uuid' => 'your-lead-uuid',
                    'status' => 'converted',
                    'converted' => true,
                    'notes' => 'Funded on 2026-06-24',
                ],
            ],
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
        $sampleIngest = $selectedCampaign && $selectedSpec
            ? array_merge(
                $this->specService->sampleRequest($selectedCampaign, $selectedSpec),
                ['sid' => $supplier->sources()->orderBy('sid')->value('sid') ?? 'your_sid'],
            )
            : null;

        $apiKeys = ApiKey::query()
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

        $settings = $supplier->affiliate_settings ?? [];

        return [
            'apiBaseUrl' => $apiBase,
            'tenantHost' => TenantResolver::portalHost($account),
            'currency' => $account->default_currency ?? 'GBP',
            'partner' => $supplier->only(['reference', 'name']),
            'sources' => $supplier->sources()->orderBy('sid')->get(['sid', 'name']),
            'campaigns' => $campaigns->map(fn (Campaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'reference' => $c->reference,
            ])->values()->all(),
            'selectedCampaign' => $selectedCampaign?->only(['id', 'name', 'reference']),
            'selectedSpec' => $selectedSpec,
            'sampleIngest' => $sampleIngest,
            'sampleStatus' => $this->specService->sampleStatusResponse(),
            'apiKeys' => $apiKeys->map(fn (ApiKey $key) => [
                'name' => $key->name,
                'prefix' => $key->key_prefix,
                'permissions' => $key->permissions ?? [],
                'last_used_at' => $key->last_used_at?->toDateTimeString(),
            ])->values()->all(),
            'postbacks' => $postbacks->map(fn (Postback $postback) => [
                'name' => $postback->name,
                'method' => strtoupper($postback->method),
                'events' => $postback->events ?? [],
                'scoped_to_you' => $postback->supplier_id === $supplier->id,
            ])->values()->all(),
            'defaultPostbackUrl' => $settings['default_postback_url'] ?? null,
            'endpoints' => $this->supplierEndpoints($apiBase),
            'guides' => $this->supplierGuides(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buyerEndpoints(Buyer $buyer, string $apiBase): array
    {
        $ref = $buyer->reference;

        return [
            [
                'key' => 'csv-export',
                'method' => 'GET',
                'path' => '/portal/buyer/leads/download',
                'type' => 'portal',
                'summary' => 'Export purchased leads (CSV)',
                'description' => 'Download up to 5,000 leads sold to your buyer account. Filter with from_date and to_date query params. Requires portal login session.',
            ],
            [
                'key' => 'feedback',
                'method' => 'POST',
                'path' => "/buyers/{$ref}/feedback",
                'type' => 'api',
                'scope' => 'buyers.manage',
                'summary' => 'Report conversion feedback',
                'description' => 'Push funded, contacted, or invalid outcomes for a lead UUID. Mirrors the buyer portal feedback form.',
            ],
            [
                'key' => 'webhooks',
                'method' => 'POST',
                'path' => '(your webhook URL)',
                'type' => 'webhook',
                'summary' => 'Receive lead events (outbound)',
                'description' => 'When configured by your platform administrator, the platform POSTs JSON to your URL on events such as lead.sold. This is push delivery — not a pull API.',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function supplierEndpoints(string $apiBase): array
    {
        return [
            [
                'key' => 'ingest',
                'method' => 'POST',
                'path' => '/leads',
                'type' => 'api',
                'scope' => 'leads.create',
                'summary' => 'Submit a lead',
                'description' => 'Real-time lead ingest. Include campaign_reference, sid, and campaign field data. Returns 202 with lead_id and queue_id unless sync=true.',
            ],
            [
                'key' => 'status',
                'method' => 'GET',
                'path' => '/leads/{lead_id}',
                'type' => 'api',
                'scope' => 'leads.read',
                'summary' => 'Poll lead status',
                'description' => 'Check sold, rejected, or unsold outcome and payout after submission. Poll every 1–2 seconds until terminal status.',
            ],
            [
                'key' => 'queue',
                'method' => 'GET',
                'path' => '/leads/queue/{queue_id}',
                'type' => 'api',
                'scope' => 'leads.read',
                'summary' => 'Poll by queue ID',
                'description' => 'Same response as GET /leads/{lead_id}. Use the queue_id from the 202 ingest response.',
            ],
            [
                'key' => 'search',
                'method' => 'POST',
                'path' => '/leads/search',
                'type' => 'api',
                'scope' => 'leads.read',
                'summary' => 'Search leads (reconciliation)',
                'description' => 'Paginated lead search by campaign_id and status. Filter results to your supplier_id in your integration layer or match UUIDs from postbacks.',
            ],
            [
                'key' => 'csv-export',
                'method' => 'GET',
                'path' => '/portal/supplier/leads/download',
                'type' => 'portal',
                'summary' => 'Export submitted leads (CSV)',
                'description' => 'Download up to 5,000 leads attributed to your supplier. Optional from_date, to_date, and sid filters. Requires portal login.',
            ],
            [
                'key' => 'postbacks',
                'method' => 'GET/POST',
                'path' => '(your postback URL)',
                'type' => 'webhook',
                'summary' => 'Conversion postbacks (outbound)',
                'description' => 'When a lead sells, the platform fires your postback URL with payout and tracking macros. Configured by your administrator.',
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
                'body' => 'Use the portal CSV export or scheduled exports to pull historical inventory. For real-time updates when leads are sold to you, ask your administrator to configure webhooks pointing at your CRM endpoint.',
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
