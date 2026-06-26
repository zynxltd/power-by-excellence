<?php

namespace App\Services\Leads;

use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Forms\HostedFormEmbedService;
use App\Services\Logging\PlatformLogger;
use App\Support\Tenancy\AccountContext;
use Illuminate\Support\Str;

class LeadIngestService
{
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
            'ip_address', 'user_agent', 'sync', 'queue_id', 'test',
        ];

        $fieldData = collect($data)->except($reserved)->all();
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

        return $lead;
    }
}
