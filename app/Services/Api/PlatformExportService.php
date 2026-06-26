<?php

namespace App\Services\Api;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Models\HostedForm;
use App\Models\Postback;
use App\Models\Supplier;
use App\Models\Webhook;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Support\Arr;

class PlatformExportService
{
    public function __construct(
        protected CampaignApiSpecService $specService,
    ) {}

    /**
     * @param  list<string>|null  $include
     * @return array<string, mixed>
     */
    public function export(Account $account, ?array $include = null): array
    {
        $sections = $this->resolveSections($include);
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'platform' => $this->platformMeta($account),
        ];

        if (in_array('campaigns', $sections, true)) {
            $payload['campaigns'] = $this->campaigns($account);
        }

        if (in_array('buyers', $sections, true)) {
            $payload['buyers'] = $this->buyers($account);
        }

        if (in_array('suppliers', $sections, true)) {
            $payload['suppliers'] = $this->suppliers($account);
        }

        if (in_array('webhooks', $sections, true)) {
            $payload['webhooks'] = $this->webhooks($account);
        }

        if (in_array('postbacks', $sections, true)) {
            $payload['postbacks'] = $this->postbacks($account);
        }

        if (in_array('forms', $sections, true)) {
            $payload['forms'] = $this->forms($account);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function campaign(Account $account, string $reference): array
    {
        $campaign = Campaign::query()
            ->where('account_id', $account->id)
            ->where('reference', $reference)
            ->with([
                'fields',
                'campaignSuppliers.supplier:id,reference,name',
                'deliveries.buyer:id,reference,name',
                'distributionConfigs',
            ])
            ->firstOrFail();

        return $this->formatCampaign($campaign);
    }

    /**
     * @param  list<string>|null  $requested
     * @return list<string>
     */
    protected function resolveSections(?array $requested): array
    {
        $available = ['campaigns', 'buyers', 'suppliers', 'webhooks', 'postbacks', 'forms'];

        if ($requested === null || $requested === []) {
            return $available;
        }

        return array_values(array_intersect($available, $requested));
    }

    /**
     * @return array<string, mixed>
     */
    protected function platformMeta(Account $account): array
    {
        $branding = $account->publicBranding();

        return [
            'id' => $account->id,
            'name' => $account->name,
            'slug' => $account->slug,
            'brand_name' => $account->brand_name,
            'domain' => $account->domain,
            'timezone' => $account->timezone,
            'default_currency' => $account->default_currency,
            'default_country' => $account->default_country,
            'is_active' => $account->is_active,
            'portal_url' => TenantResolver::portalUrl($account),
            'api_base_url' => TenantResolver::apiBaseUrl($account),
            'branding' => $branding,
            'settings' => $this->sanitizeSettings($account->settings ?? []),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function campaigns(Account $account): array
    {
        return Campaign::query()
            ->where('account_id', $account->id)
            ->with([
                'fields',
                'campaignSuppliers.supplier:id,reference,name',
                'deliveries.buyer:id,reference,name',
                'distributionConfigs',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Campaign $campaign) => $this->formatCampaign($campaign))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatCampaign(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'reference' => $campaign->reference,
            'name' => $campaign->name,
            'type' => $campaign->type,
            'status' => $campaign->status,
            'country' => $campaign->country,
            'currency' => $campaign->currency,
            'vertical_id' => $campaign->vertical_id,
            'sell_mode' => $campaign->sell_mode,
            'max_sells' => $campaign->max_sells,
            'floor_price' => (float) $campaign->floor_price,
            'bidding_mode' => $campaign->bidding_mode,
            'ping_timeout_ms' => $campaign->ping_timeout_ms,
            'payout_supplier_on' => $campaign->payout_supplier_on,
            'payout_amount' => (float) $campaign->payout_amount,
            'caps' => $campaign->caps,
            'dedupe_config' => $campaign->dedupe_config,
            'validation_config' => $campaign->validation_config,
            'use_advanced_distribution' => $campaign->use_advanced_distribution,
            'multi_geo' => $campaign->multi_geo,
            'geo_countries' => $campaign->geo_countries,
            'reference_locked' => $campaign->reference_locked,
            'fields' => $campaign->fields->map(fn ($field) => [
                'name' => $field->name,
                'label' => $field->label,
                'type' => $field->type,
                'required' => $field->required,
                'ping_field' => $field->ping_field,
                'validation' => $field->validation,
                'sort_order' => $field->sort_order,
            ])->values()->all(),
            'api_spec' => $this->specService->defaultSpec($campaign),
            'suppliers' => $campaign->campaignSuppliers->map(fn ($link) => [
                'supplier_reference' => $link->supplier?->reference,
                'supplier_name' => $link->supplier?->name,
                'caps' => $link->caps,
                'payout_amount' => $link->payout_amount !== null ? (float) $link->payout_amount : null,
            ])->values()->all(),
            'deliveries' => $campaign->deliveries->map(fn (Delivery $delivery) => $this->formatDelivery($delivery, $campaign->reference))->values()->all(),
            'distribution_configs' => $campaign->distributionConfigs->map(fn (DistributionConfig $config) => [
                'id' => $config->id,
                'name' => $config->name,
                'is_active' => $config->is_active,
                'is_locked' => $config->is_locked,
                'config' => $config->config,
            ])->values()->all(),
            'updated_at' => $campaign->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buyers(Account $account): array
    {
        return Buyer::query()
            ->where('account_id', $account->id)
            ->with(['deliveries.campaign:id,reference,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Buyer $buyer) => [
                'id' => $buyer->id,
                'reference' => $buyer->reference,
                'name' => $buyer->name,
                'email' => $buyer->email,
                'status' => $buyer->status,
                'credit_balance' => (float) $buyer->credit_balance,
                'currency' => $buyer->resolvedCurrency(),
                'caps' => $buyer->caps,
                'schedule' => $buyer->schedule,
                'settings' => $this->sanitizeSettings($buyer->settings ?? []),
                'deliveries' => $buyer->deliveries->map(fn (Delivery $delivery) => $this->formatDelivery(
                    $delivery,
                    $delivery->campaign?->reference,
                ))->values()->all(),
                'updated_at' => $buyer->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatDelivery(Delivery $delivery, ?string $campaignReference = null): array
    {
        return [
            'id' => $delivery->id,
            'name' => $delivery->name,
            'campaign_reference' => $campaignReference,
            'buyer_reference' => $delivery->buyer?->reference,
            'method' => $delivery->method?->value ?? $delivery->method,
            'trigger_type' => $delivery->trigger_type,
            'status' => $delivery->status,
            'advanced_distribution_only' => $delivery->advanced_distribution_only,
            'priority' => $delivery->priority,
            'weight' => $delivery->weight,
            'tier' => $delivery->tier,
            'routing_mode' => $delivery->routing_mode,
            'revenue_type' => $delivery->revenue_type,
            'revenue_amount' => $delivery->revenue_amount !== null ? (float) $delivery->revenue_amount : null,
            'revenue_rules' => $delivery->revenue_rules,
            'cap_type' => $delivery->cap_type,
            'caps' => $delivery->caps,
            'config' => $delivery->config,
            'eligibility_rules' => $delivery->eligibility_rules,
            'schedule' => $delivery->schedule,
            'location_filter' => $delivery->location_filter,
            'on_success_delivery_id' => $delivery->on_success_delivery_id,
            'on_failure_delivery_id' => $delivery->on_failure_delivery_id,
            'updated_at' => $delivery->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function suppliers(Account $account): array
    {
        return Supplier::query()
            ->where('account_id', $account->id)
            ->with(['sources.subSuppliers'])
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'reference' => $supplier->reference,
                'name' => $supplier->name,
                'status' => $supplier->status,
                'affiliate_settings' => $supplier->affiliate_settings,
                'sources' => $supplier->sources->map(fn ($source) => [
                    'id' => $source->id,
                    'sid' => $source->sid,
                    'name' => $source->name,
                    'caps' => $source->caps,
                    'payout_override' => $source->payout_override !== null ? (float) $source->payout_override : null,
                    'sub_suppliers' => $source->subSuppliers->map(fn ($sub) => [
                        'ssid' => $sub->ssid,
                        'name' => $sub->name,
                    ])->values()->all(),
                ])->values()->all(),
                'updated_at' => $supplier->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function webhooks(Account $account): array
    {
        return Webhook::query()
            ->where('account_id', $account->id)
            ->with('buyer:id,reference,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Webhook $webhook) => [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'url' => $webhook->url,
                'events' => $webhook->events,
                'is_active' => $webhook->is_active,
                'buyer_reference' => $webhook->buyer?->reference,
                'has_secret' => filled($webhook->secret),
                'config' => $webhook->config,
                'updated_at' => $webhook->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function postbacks(Account $account): array
    {
        return Postback::query()
            ->where('account_id', $account->id)
            ->with(['supplier:id,reference,name', 'campaign:id,reference,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Postback $postback) => [
                'id' => $postback->id,
                'name' => $postback->name,
                'url' => $postback->url,
                'method' => $postback->method,
                'events' => $postback->events,
                'is_active' => $postback->is_active,
                'supplier_reference' => $postback->supplier?->reference,
                'campaign_reference' => $postback->campaign?->reference,
                'config' => $postback->config,
                'updated_at' => $postback->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function forms(Account $account): array
    {
        return HostedForm::query()
            ->where('account_id', $account->id)
            ->with('campaign:id,reference,name')
            ->orderBy('name')
            ->get()
            ->map(fn (HostedForm $form) => [
                'id' => $form->id,
                'name' => $form->name,
                'slug' => $form->slug,
                'campaign_reference' => $form->campaign?->reference,
                'is_active' => $form->is_active,
                'embed_url' => $form->embedUrl(),
                'iframe_snippet' => $form->iframeSnippet(),
                'config' => $form->config,
                'updated_at' => $form->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function sanitizeSettings(array $settings): array
    {
        $sanitized = $settings;

        unset($sanitized['validation']['ipqs']['api_key']);

        if (isset($sanitized['validation']['ipqs']) && $sanitized['validation']['ipqs'] === []) {
            unset($sanitized['validation']['ipqs']);
        }

        if (isset($sanitized['validation']) && $sanitized['validation'] === []) {
            unset($sanitized['validation']);
        }

        return Arr::except($sanitized, [
            'billing_locked_at',
            'billing_lock_reason',
        ]);
    }
}
