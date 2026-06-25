<?php

namespace App\Services\Leads;

use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Source;
use App\Models\SubSupplier;
use App\Models\Supplier;
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

        $supplierId = $apiKey?->supplier_id;
        $sourceId = null;
        $subSupplierId = null;

        if (! empty($data['sid']) && $supplierId) {
            $source = Source::whereHas('supplier', fn ($q) => $q->where('account_id', $campaign->account_id))
                ->where('supplier_id', $supplierId)
                ->where('sid', $data['sid'])
                ->first();
            $sourceId = $source?->id;
        }

        if (! empty($data['ssid']) && $sourceId) {
            $sub = SubSupplier::where('source_id', $sourceId)->where('ssid', $data['ssid'])->first();
            $subSupplierId = $sub?->id;
        }

        $reserved = [
            'campaign_reference', 'campaign_id', 'sid', 'ssid', 'source',
            'ip_address', 'user_agent', 'sync', 'queue_id',
        ];

        $fieldData = collect($data)->except($reserved)->all();

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplierId,
            'source_id' => $sourceId,
            'sub_supplier_id' => $subSupplierId,
            'queue_id' => $data['queue_id'] ?? ('q_'.Str::random(16)),
            'field_data' => $fieldData,
            'sid' => $data['sid'] ?? null,
            'ssid' => $data['ssid'] ?? null,
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
