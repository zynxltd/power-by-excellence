<?php

namespace App\Services\Integrations;

use App\Jobs\ProcessLeadJob;
use App\Models\Account;
use App\Models\Campaign;
use App\Services\Leads\LeadIngestService;
use App\Support\Tenancy\AccountContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LeadSourceIngestService
{
    public function __construct(
        protected LeadIngestService $ingest,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $payload
     * @return array{lead_id: string, queue_id: string, status: string}
     */
    public function ingest(Account $account, array $config, array $payload, string $provider): array
    {
        $campaign = $this->resolveCampaign($account, $config);
        $fields = $this->extractFields($provider, $config, $payload);

        AccountContext::set($account);

        $lead = $this->ingest->ingest([
            'campaign_id' => $campaign->id,
            'source' => $provider,
            ...$fields,
        ]);

        ProcessLeadJob::dispatch($lead->id);

        return [
            'lead_id' => $lead->uuid,
            'queue_id' => $lead->queue_id,
            'status' => 'queued',
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function extractFields(string $provider, array $config, array $payload): array
    {
        if ($provider === 'facebook') {
            $fromGraph = $this->facebookFieldsFromGraph($config, $payload);
            if ($fromGraph !== null) {
                return $this->applyFieldMapping($fromGraph, $config['field_mapping'] ?? []);
            }
        }

        $flat = $this->flattenPayload($payload);
        $mapped = $this->applyFieldMapping($flat, $config['field_mapping'] ?? []);

        return $mapped !== [] ? $mapped : $flat;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function facebookFieldsFromGraph(array $config, array $payload): ?array
    {
        $leadgenId = $this->facebookLeadgenId($payload);
        $token = $config['page_access_token'] ?? null;

        if (! $leadgenId || ! $token) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get("https://graph.facebook.com/v21.0/{$leadgenId}", [
                'access_token' => $token,
                'fields' => 'field_data,created_time,ad_id,form_id',
            ]);

            if (! $response->successful()) {
                Log::warning('Facebook Graph API lead fetch failed', [
                    'leadgen_id' => $leadgenId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();
            $fields = [];

            foreach ($data['field_data'] ?? [] as $item) {
                $name = strtolower((string) ($item['name'] ?? ''));
                $values = $item['values'] ?? [];
                if ($name !== '' && $values !== []) {
                    $fields[$name] = is_array($values) ? ($values[0] ?? '') : $values;
                }
            }

            if (! empty($data['ad_id'])) {
                $fields['ad_id'] = $data['ad_id'];
            }
            if (! empty($data['form_id'])) {
                $fields['form_id'] = $data['form_id'];
            }

            return $fields;
        } catch (\Throwable $e) {
            Log::warning('Facebook Graph API lead fetch error', [
                'leadgen_id' => $leadgenId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function facebookLeadgenId(array $payload): ?string
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') === 'leadgen') {
                    $id = $change['value']['leadgen_id'] ?? null;
                    if ($id) {
                        return (string) $id;
                    }
                }
            }
        }

        return $payload['leadgen_id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function flattenPayload(array $payload): array
    {
        $reserved = ['object', 'entry', 'hub_mode', 'hub_challenge', 'hub_verify_token'];

        $flat = collect($payload)
            ->except($reserved)
            ->filter(fn ($value) => is_scalar($value) || $value === null)
            ->map(fn ($value) => $value === null ? '' : $value)
            ->all();

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                if (is_array($value)) {
                    foreach ($value as $key => $item) {
                        if (is_scalar($item)) {
                            $flat[$key] = $item;
                        }
                    }
                }
            }
        }

        if (isset($payload['user_column_data']) && is_array($payload['user_column_data'])) {
            foreach ($payload['user_column_data'] as $column) {
                $key = strtolower((string) ($column['column_id'] ?? $column['column_name'] ?? ''));
                if ($key !== '') {
                    $flat[$key] = $column['string_value'] ?? $column['value'] ?? '';
                }
            }
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, string>  $mapping
     * @return array<string, mixed>
     */
    protected function applyFieldMapping(array $fields, array $mapping): array
    {
        if ($mapping === []) {
            return $fields;
        }

        $mapped = [];
        foreach ($mapping as $source => $target) {
            if (Arr::has($fields, $source)) {
                $mapped[$target] = Arr::get($fields, $source);
            }
        }

        return $mapped !== [] ? $mapped : $fields;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function resolveCampaign(Account $account, array $config): Campaign
    {
        $campaignId = $config['campaign_id'] ?? null;
        abort_unless($campaignId, 422, 'No target campaign configured for this integration.');

        return Campaign::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('id', $campaignId)
            ->firstOrFail();
    }
}
