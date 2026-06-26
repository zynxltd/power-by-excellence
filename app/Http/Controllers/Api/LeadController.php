<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLeadJob;
use App\Models\ApiKey;
use App\Models\Lead;
use App\Services\Leads\LeadIngestService;
use App\Services\Leads\LeadPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function store(Request $request, LeadIngestService $ingest): JsonResponse
    {
        $validated = $request->validate([
            'campaign_reference' => 'required_without:campaign_id|string',
            'campaign_id' => 'required_without:campaign_reference',
            'sync' => 'boolean',
            'test' => 'boolean',
        ]);

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        $lead = $ingest->ingest($request->all(), $apiKey);

        if ($request->boolean('sync')) {
            $lead = app(LeadPipeline::class)->process($lead);

            return response()->json($this->formatResponse($lead));
        }

        ProcessLeadJob::dispatch($lead->id);

        return response()->json([
            'status' => 'queued',
            'queue_id' => $lead->queue_id,
            'lead_id' => $lead->uuid,
        ], 202);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $lead = $this->findLeadForApi($request, $uuid);
        $lead->load(['financials', 'soldToBuyer', 'deliveryLogs']);

        return response()->json($this->formatResponse($lead));
    }

    public function queueStatus(Request $request, string $queueId): JsonResponse
    {
        $lead = Lead::where('queue_id', $queueId)->with(['financials', 'soldToBuyer'])->firstOrFail();
        $this->ensureLeadBelongsToApiAccount($request, $lead);

        return response()->json($this->formatResponse($lead));
    }

    public function search(Request $request): JsonResponse
    {
        $leads = Lead::query()
            ->when($request->campaign_id, fn ($q, $id) => $q->where('campaign_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('received_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($leads);
    }

    public function reprocess(Request $request, string $uuid): JsonResponse
    {
        $lead = $this->findLeadForApi($request, $uuid);
        ProcessLeadJob::dispatch($lead->id);

        return response()->json(['status' => 'queued', 'queue_id' => $lead->queue_id]);
    }

    protected function findLeadForApi(Request $request, string $uuid): Lead
    {
        $lead = Lead::where('uuid', $uuid)->firstOrFail();
        $this->ensureLeadBelongsToApiAccount($request, $lead);

        return $lead;
    }

    protected function ensureLeadBelongsToApiAccount(Request $request, Lead $lead): void
    {
        $accountId = $request->attributes->get('account')?->id;

        if ($accountId && $lead->account_id !== $accountId) {
            abort(404);
        }
    }

    protected function resolveRedirectUrl(Lead $lead): ?string
    {
        $soldDelivery = $lead->deliveryLogs()
            ->with('delivery:id,config,campaign_id')
            ->where('status', 'success')
            ->latest()
            ->first();

        if (! $soldDelivery) {
            return null;
        }

        $lead->loadMissing('campaign.distributionConfigs');

        if ($lead->campaign?->use_advanced_distribution) {
            $config = $lead->campaign->distributionConfigs->firstWhere('is_active', true);

            foreach ($config?->config['groups'] ?? [] as $group) {
                if (in_array($soldDelivery->delivery_id, $group['delivery_ids'] ?? [], true)) {
                    if (filled($group['redirect_url'] ?? null)) {
                        return $group['redirect_url'];
                    }

                    break;
                }
            }
        }

        return $soldDelivery->delivery?->config['redirect_url']
            ?? $soldDelivery->delivery?->config['accept_url']
            ?? null;
    }

    protected function formatResponse(Lead $lead): array
    {
        $redirectUrl = $this->resolveRedirectUrl($lead);

        return [
            'status' => $lead->status->value,
            'lead_id' => $lead->uuid,
            'queue_id' => $lead->queue_id,
            'test_mode' => (bool) ($lead->metadata['test_mode'] ?? false),
            'reject_reason' => $lead->reject_reason,
            'buyer_reference' => $lead->soldToBuyer?->reference,
            'revenue' => $lead->financials?->revenue,
            'currency' => $lead->financials?->currency ?? $lead->campaign?->currency,
            'redirect_url' => $lead->status->value === 'sold' ? $redirectUrl : null,
            'received_at' => $lead->received_at?->toIso8601String(),
            'distributed_at' => $lead->distributed_at?->toIso8601String(),
        ];
    }
}
