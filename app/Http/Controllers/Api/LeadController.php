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

    public function show(string $uuid): JsonResponse
    {
        $lead = Lead::where('uuid', $uuid)->with(['financials', 'soldToBuyer', 'deliveryLogs'])->firstOrFail();

        return response()->json($this->formatResponse($lead));
    }

    public function queueStatus(string $queueId): JsonResponse
    {
        $lead = Lead::where('queue_id', $queueId)->with(['financials', 'soldToBuyer'])->firstOrFail();

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

    public function reprocess(string $uuid): JsonResponse
    {
        $lead = Lead::where('uuid', $uuid)->firstOrFail();
        ProcessLeadJob::dispatch($lead->id);

        return response()->json(['status' => 'queued', 'queue_id' => $lead->queue_id]);
    }

    protected function formatResponse(Lead $lead): array
    {
        $soldDelivery = $lead->deliveryLogs()
            ->with('delivery:id,config')
            ->where('status', 'success')
            ->latest()
            ->first();

        $redirectUrl = $soldDelivery?->delivery?->config['redirect_url']
            ?? $soldDelivery?->delivery?->config['accept_url']
            ?? null;

        return [
            'status' => $lead->status->value,
            'lead_id' => $lead->uuid,
            'queue_id' => $lead->queue_id,
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
