<?php

namespace App\Services\Leads;

use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Forms\HostedFormEmbedService;
use App\Services\Logging\PlatformLogger;
use App\Support\Tenancy\AccountContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LeadIngestService
{
    /** @var list<string> */
    protected const LEAD_SOURCE_PROVIDERS = ['facebook', 'google', 'tiktok'];

    /**
     * @param  array<int|string, mixed>  $mapping
     * @return array<string, string>
     */
    public function normalizeFieldMapping(array $mapping): array
    {
        if ($mapping === []) {
            return [];
        }

        if (array_is_list($mapping)) {
            $normalized = [];
            foreach ($mapping as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $source = trim((string) ($row['source'] ?? ''));
                $target = trim((string) ($row['target'] ?? ''));

                if ($source !== '' && $target !== '') {
                    $normalized[$source] = $target;
                }
            }

            return $normalized;
        }

        $normalized = [];
        foreach ($mapping as $source => $target) {
            $sourceKey = trim((string) $source);
            $targetKey = trim((string) $target);

            if ($sourceKey !== '' && $targetKey !== '') {
                $normalized[$sourceKey] = $targetKey;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<int|string, mixed>  $mapping
     * @return array<string, mixed>
     */
    public function applyLeadSourceFieldMapping(array $fields, array $mapping): array
    {
        $mapping = $this->normalizeFieldMapping($mapping);

        if ($mapping === []) {
            return $fields;
        }

        $fieldsByKey = [];
        foreach ($fields as $key => $value) {
            $fieldsByKey[strtolower((string) $key)] = $value;
        }

        $mapped = [];
        $mappedSources = [];

        foreach ($mapping as $source => $target) {
            $sourceKey = strtolower((string) $source);
            $mappedSources[] = $sourceKey;

            if (array_key_exists($sourceKey, $fieldsByKey)) {
                $mapped[(string) $target] = $fieldsByKey[$sourceKey];
            } elseif (Arr::has($fields, $source)) {
                $mapped[(string) $target] = Arr::get($fields, $source);
            }
        }

        foreach ($fieldsByKey as $key => $value) {
            if (in_array($key, $mappedSources, true)) {
                continue;
            }

            $mapped[$key] = $value;
        }

        return $mapped !== [] ? $mapped : $fields;
    }

    public function ingest(array $data, ?ApiKey $apiKey = null): Lead
    {
        $reference = $data['campaign_reference'] ?? $data['campaign_id'] ?? null;

        $campaign = Campaign::withoutGlobalScopes()
            ->when($apiKey, fn ($q) => $q->where('account_id', $apiKey->account_id))
            ->where(function ($q) use ($reference) {
                $q->where('reference', $reference)->orWhere('id', $reference);
            })
            ->firstOrFail();

        AccountContext::set($campaign->account);

        if (! app(\App\Services\Billing\AccountBillingService::class)->canAcceptLeads($campaign->account)) {
            abort(402, 'Account billing is locked. Lead ingest is suspended.');
        }

        $supplierContext = $apiKey?->supplier_id
            ? app(HostedFormEmbedService::class)->resolveSupplierContext($campaign, [
                'supplier_id' => $apiKey->supplier_id,
                'sid' => $data['sid'] ?? null,
                'ssid' => $data['ssid'] ?? null,
                'subid' => $data['subid'] ?? null,
            ])
            : app(HostedFormEmbedService::class)->resolveSupplierContext($campaign, $data);

        $reserved = [
            'campaign_reference', 'campaign_id', 'sid', 'ssid', 'subid', 'source',
            'supplier_id', 'click_id', 'gclid', 'fbclid',
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
            'embed', '_embed_parent',
            'ip_address', 'user_agent', 'sync', 'queue_id', 'test', 'field_mapping',
        ];

        $fieldMapping = $data['field_mapping'] ?? null;
        $provider = $data['source'] ?? null;

        if ($fieldMapping === null && $provider && in_array($provider, self::LEAD_SOURCE_PROVIDERS, true)) {
            $fieldMapping = $campaign->account->settings['lead_sources'][$provider]['field_mapping'] ?? [];
        }

        $fieldData = collect($data)->except($reserved)->all();
        $fieldData = $this->applyLeadSourceFieldMapping($fieldData, is_array($fieldMapping) ? $fieldMapping : []);
        $isTest = filter_var($data['test'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $trackingMeta = app(HostedFormEmbedService::class)->trackingMetadata($data);
        $metadata = array_filter(array_merge(
            $isTest ? ['test_mode' => true] : [],
            $trackingMeta,
        )) ?: null;

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplierContext['supplier_id'],
            'source_id' => $supplierContext['source_id'],
            'sub_supplier_id' => $supplierContext['sub_supplier_id'],
            'queue_id' => $data['queue_id'] ?? ('q_'.Str::random(16)),
            'field_data' => $fieldData,
            'metadata' => $metadata,
            'sid' => $supplierContext['sid'],
            'ssid' => $supplierContext['ssid'],
            'source' => $data['source'] ?? null,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
            'received_at' => now(),
        ]);

        if (! $campaign->reference_locked) {
            $campaign->update(['reference_locked' => true]);
        }

        PlatformLogger::leadEvent($lead, 'lead.ingested', 'Lead ingested via API');

        $clickUuid = $data['click_id'] ?? data_get($metadata, 'click_id');
        if ($clickUuid) {
            app(\App\Services\ClickTrack\ClickLogService::class)->attachLeadByClickUuid($lead, $clickUuid);
        }

        return $lead;
    }
}
