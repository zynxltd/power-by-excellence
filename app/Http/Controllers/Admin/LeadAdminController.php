<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessLeadJob;
use App\Support\LeadQueueMetrics;
use App\Support\Queue\LeadJobDispatcher;
use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Leads\LeadQualityService;
use App\Support\Admin\CampaignWorkflow;
use App\Support\CsvExport;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LeadAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $baseQuery = $this->filteredQuery($request);

        $query = (clone $baseQuery)
            ->with(['campaign', 'campaign.account', 'soldToBuyer', 'financials', 'account'])
            ->orderByDesc('received_at');

        $leads = $query->paginate(25)->withQueryString();
        $leads->getCollection()->transform(function (Lead $lead) {
            $lead->setAttribute('quality', LeadQualityService::analyzeLead($lead));

            return $lead;
        });

        return Inertia::render('Admin/Leads/Index', [
            'leads' => $leads,
            'campaigns' => Campaign::orderBy('name')->get(['id', 'name', 'reference']),
            'filters' => $request->only([
                'status',
                'campaign_id',
                'account_id',
                'search',
                'from_date',
                'to_date',
                'quality_min',
                'quality_max',
                'validation',
                'redirect',
            ]),
            'statuses' => ['pending', 'processing', 'sold', 'unsold', 'rejected', 'quarantined', 'duplicate'],
            'pipelineSummary' => $this->pipelineSummary($baseQuery),
            'showTenantColumn' => $this->showTenantColumn($request),
            'campaignWorkflow' => CampaignWorkflow::fromId($request->integer('campaign_id') ?: null),
        ]);
    }

    public function show(Lead $lead): Response
    {
        $this->ensureLeadAccessible($lead);

        $lead->load([
            'campaign',
            'campaign.account',
            'events',
            'deliveryLogs' => fn ($q) => $q
                ->with(['delivery:id,name,method,tier,campaign_id', 'buyer:id,name'])
                ->orderBy('created_at'),
            'financials',
            'soldToBuyer',
            'account',
        ]);

        $lead->setRelation(
            'deliveryLogs',
            $lead->deliveryLogs
                ->sortBy(fn ($log) => sprintf('%05d-%s', $log->delivery?->tier ?? 9999, $log->created_at))
                ->values()
        );

        $processingMs = collect($lead->events)
            ->firstWhere('event_type', 'pipeline.completed')
            ?->payload['duration_ms'] ?? null;

        $prevId = Lead::query()
            ->where('campaign_id', $lead->campaign_id)
            ->where('id', '<', $lead->id)
            ->orderByDesc('id')
            ->value('id');
        $nextId = Lead::query()
            ->where('campaign_id', $lead->campaign_id)
            ->where('id', '>', $lead->id)
            ->orderBy('id')
            ->value('id');

        return Inertia::render('Admin/Leads/Show', [
            'lead' => $lead,
            'leadQuality' => LeadQualityService::analyzeLead($lead),
            'campaignWorkflow' => CampaignWorkflow::forCampaign($lead->campaign),
            'pipelineStages' => $this->pipelineStages($lead),
            'outcomeDetail' => $this->outcomeDetail($lead),
            'processingMs' => $processingMs,
            'navigation' => [
                'prev_id' => $prevId,
                'next_id' => $nextId,
            ],
        ]);
    }

    public function releaseQuarantine(Lead $lead): RedirectResponse
    {
        $this->ensureLeadAccessible($lead);
        abort_unless($lead->status === LeadStatus::Quarantined, 422);

        $reason = $lead->metadata['quarantine_reason'] ?? null;
        if ($reason === 'validation'
            || ! empty($lead->metadata['email_validation'])
            || ! empty($lead->metadata['hlr_validation'])
            || ! empty($lead->metadata['field_validation'])) {
            abort(422, 'Validation holds must be rejected — they cannot be released back into distribution.');
        }

        $lead->update(['status' => LeadStatus::Accepted, 'quarantined_until' => null]);
        LeadJobDispatcher::dispatch($lead->id);

        return back()->with('success', 'Lead released from quarantine and queued for distribution.');
    }

    public function rejectQuarantine(Lead $lead): RedirectResponse
    {
        $this->ensureLeadAccessible($lead);
        abort_unless($lead->status === LeadStatus::Quarantined, 422);
        $lead->update([
            'status' => LeadStatus::Rejected,
            'reject_reason' => 'Quarantine rejected by admin',
            'quarantined_until' => null,
        ]);

        return back()->with('success', 'Quarantined lead rejected.');
    }

    public function repost(Lead $lead): RedirectResponse
    {
        $this->ensureLeadAccessible($lead);

        if (! in_array($lead->status, [LeadStatus::Unsold, LeadStatus::Quarantined], true)) {
            return back()->with('error', 'Only unsold or quarantined leads can be reposted.');
        }

        if ($lead->status === LeadStatus::Quarantined) {
            $reason = $lead->metadata['quarantine_reason'] ?? null;
            if ($reason === 'validation') {
                return back()->with('error', 'Validation holds must be released or rejected — not reposted.');
            }
        }

        $attempts = (int) ($lead->metadata['repost_attempts'] ?? 0);
        $max = (int) config('platform.max_repost_attempts', 3);
        if ($attempts >= $max) {
            return back()->with('error', "Maximum repost attempts ({$max}) reached.");
        }

        $lead->update([
            'status' => LeadStatus::Accepted,
            'quarantined_until' => null,
            'reject_reason' => null,
            'sold_to_buyer_id' => null,
            'distributed_at' => null,
            'metadata' => array_merge($lead->metadata ?? [], [
                'repost_attempts' => $attempts + 1,
                'last_reposted_at' => now()->toIso8601String(),
            ]),
        ]);

        LeadJobDispatcher::dispatch($lead->id);

        return back()->with('success', 'Lead queued for repost through the ping tree.');
    }

    public function export(Request $request)
    {
        $leads = (clone $this->filteredQuery($request))
            ->with(['campaign:id,name,reference', 'financials', 'soldToBuyer:id,name'])
            ->orderByDesc('received_at')
            ->limit(5000)
            ->get();

        $csv = "uuid,campaign,status,quality_score,email_status,hlr_status,firstname,lastname,email,phone,zipcode,revenue,buyer,received_at,distributed_at\n";

        foreach ($leads as $lead) {
            $quality = LeadQualityService::analyzeLead($lead);
            $csv .= CsvExport::escapeRow([
                $lead->uuid,
                $lead->campaign?->reference ?? '',
                $lead->status->value,
                $quality['score'],
                $quality['email']['label'],
                $quality['hlr']['label'],
                $lead->getField('firstname'),
                $lead->getField('lastname'),
                $lead->getField('email'),
                $lead->getField('phone1'),
                $lead->getField('zipcode'),
                $lead->financials?->revenue ?? 0,
                $lead->soldToBuyer?->name ?? '',
                $lead->received_at,
                $lead->distributed_at,
            ])."\n";
        }

        return CsvExport::download($csv, 'leads-'.now()->format('Y-m-d-His').'.csv');
    }

    protected function filteredQuery(Request $request)
    {
        $query = Lead::query();

        if ($request->filled('status')) {
            if ($request->status === 'processing') {
                $query->whereIn('status', LeadStatus::processingValues());
            } elseif ($request->status === 'duplicate') {
                $query->where('status', LeadStatus::Duplicate);
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', (int) $request->account_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('uuid', 'like', "%{$search}%")
                    ->orWhere('queue_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('received_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('received_at', '<=', $request->to_date);
        }

        if ($request->filled('quality_min')) {
            $min = max(0, min(100, (int) $request->input('quality_min')));
            $query->whereRaw("CAST(json_extract(metadata, '$.quality_score') AS INTEGER) >= ?", [$min]);
        }

        if ($request->filled('quality_max')) {
            $max = max(0, min(100, (int) $request->input('quality_max')));
            $query->whereRaw("CAST(json_extract(metadata, '$.quality_score') AS INTEGER) <= ?", [$max]);
        }

        if ($request->filled('validation')) {
            match ($request->string('validation')->toString()) {
                'email_checked' => $query->whereNotNull('metadata->email_validation'),
                'email_failed' => $query->where('metadata->email_validation->passed', false),
                'email_passed' => $query->where('metadata->email_validation->passed', true),
                'hlr_checked' => $query->whereNotNull('metadata->hlr_validation'),
                'hlr_failed' => $query->where('metadata->hlr_validation->passed', false),
                'hlr_passed' => $query->where('metadata->hlr_validation->passed', true),
                'ip_checked' => $query->whereNotNull('metadata->ip_validation'),
                'ip_failed' => $query->where('metadata->ip_validation->passed', false),
                'ip_passed' => $query->where('metadata->ip_validation->passed', true),
                default => null,
            };
        }

        if ($request->filled('redirect')) {
            match ($request->string('redirect')->toString()) {
                'offered' => $query->whereNotNull('redirect_offered_at'),
                'followed' => $query->whereNotNull('redirect_followed_at'),
                default => null,
            };
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    protected function pipelineSummary($baseQuery): array
    {
        $counts = (clone $baseQuery)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($counts);

        return LeadQueueMetrics::pipelineSummary($counts, $total);
    }

    protected function showTenantColumn(Request $request): bool
    {
        return ! AccountContext::id() && $request->user()?->isSuperAdmin();
    }

    protected function ensureLeadAccessible(Lead $lead): void
    {
        $accountId = AccountContext::id()
            ?? request()->attributes->get('host_account')?->id
            ?? request()->attributes->get('account')?->id;

        if ($accountId !== null && (int) $lead->account_id !== (int) $accountId) {
            abort(404);
        }
    }

    /**
     * @return list<array{key: string, label: string, state: string, detail: ?string, tab: ?string}>
     */
    protected function pipelineStages(Lead $lead): array
    {
        $status = $lead->status instanceof LeadStatus ? $lead->status->value : (string) $lead->status;

        $stages = [
            ['key' => 'received', 'label' => 'Received'],
            ['key' => 'validation', 'label' => 'Validation'],
            ['key' => 'distribution', 'label' => 'Distribution'],
            ['key' => 'outcome', 'label' => 'Outcome'],
        ];

        $current = match ($status) {
            'pending' => 'received',
            'validating' => 'validation',
            'accepted', 'distributing' => 'distribution',
            'quarantined' => 'validation',
            'duplicate', 'rejected' => 'validation',
            'sold', 'unsold' => 'outcome',
            default => 'received',
        };

        $order = ['received', 'validation', 'distribution', 'outcome'];
        $currentIdx = array_search($current, $order, true);
        $outcomeDetail = $this->outcomeDetail($lead);

        $stageDetails = [
            'received' => $lead->received_at ? 'Lead ingested' : null,
            'validation' => match ($status) {
                'rejected' => $lead->reject_reason ?? 'Validation failed',
                'duplicate' => 'Duplicate of existing lead',
                'quarantined' => 'Held for review',
                default => in_array($status, ['sold', 'unsold', 'validating', 'accepted', 'distributing'], true) ? 'Passed' : null,
            },
            'distribution' => $lead->deliveryLogs->isNotEmpty()
                ? $lead->deliveryLogs->count().' delivery attempt(s)'
                : (in_array($status, ['distributing'], true) ? 'Routing in progress' : null),
            'outcome' => $outcomeDetail['summary'] ?? null,
        ];

        $stageTabs = [
            'received' => 'events',
            'validation' => in_array($status, ['rejected', 'duplicate', 'quarantined'], true) ? 'events' : null,
            'distribution' => 'deliveries',
            'outcome' => in_array($status, ['sold', 'unsold', 'rejected'], true) ? 'deliveries' : 'events',
        ];

        return collect($stages)->map(function (array $stage, int $i) use ($currentIdx, $status, $stageDetails, $stageTabs) {
            $state = 'upcoming';
            if ($i < $currentIdx) {
                $state = 'complete';
            } elseif ($i === $currentIdx) {
                $state = in_array($status, ['rejected', 'duplicate', 'quarantined', 'unsold'], true) ? 'error' : 'current';
            }

            return array_merge($stage, [
                'state' => $state,
                'detail' => $stageDetails[$stage['key']] ?? null,
                'tab' => $stageTabs[$stage['key']] ?? null,
            ]);
        })->all();
    }

    /**
     * @return array{title: string, summary: string, reason: ?string, hints: list<string>, delivery_stats: array<string, int>}
     */
    protected function outcomeDetail(Lead $lead): array
    {
        $status = $lead->status instanceof LeadStatus ? $lead->status->value : (string) $lead->status;

        $deliveryStats = $lead->deliveryLogs
            ->groupBy('status')
            ->map->count()
            ->all();

        $lastUnsoldEvent = collect($lead->events)
            ->first(fn ($e) => in_array($e->event_type, ['lead.unsold', 'lead.rejected', 'lead.duplicate'], true));

        $hints = [];
        if (($deliveryStats['outbid'] ?? 0) > 0) {
            $hints[] = $deliveryStats['outbid'].' buyer(s) outbid — bid below winning price or floor.';
        }
        if (($deliveryStats['failed'] ?? 0) > 0) {
            $hints[] = $deliveryStats['failed'].' delivery attempt(s) failed — check buyer API logs.';
        }
        if (($deliveryStats['skipped'] ?? 0) > 0) {
            $hints[] = $deliveryStats['skipped'].' delivery attempt(s) skipped — caps, filters, or schedule.';
        }
        if ($lead->deliveryLogs->isEmpty() && $status === 'unsold') {
            $hints[] = 'No buyers were pinged — check ping tree tiers and delivery eligibility.';
        }

        return match ($status) {
            'sold' => [
                'title' => 'Sold',
                'summary' => 'Lead sold to '.($lead->soldToBuyer?->name ?? 'buyer'),
                'reason' => null,
                'hints' => $hints,
                'delivery_stats' => $deliveryStats,
            ],
            'unsold' => [
                'title' => 'Unsold',
                'summary' => 'No buyer accepted this lead after distribution',
                'reason' => $lead->reject_reason ?? $lastUnsoldEvent?->message,
                'hints' => $hints,
                'delivery_stats' => $deliveryStats,
            ],
            'rejected' => [
                'title' => 'Rejected',
                'summary' => 'Lead failed validation or was rejected',
                'reason' => $lead->reject_reason ?? $lastUnsoldEvent?->message,
                'hints' => $hints,
                'delivery_stats' => $deliveryStats,
            ],
            'duplicate' => [
                'title' => 'Duplicate',
                'summary' => 'Matched an existing lead in this campaign',
                'reason' => $lead->reject_reason ?? $lastUnsoldEvent?->message,
                'hints' => $hints,
                'delivery_stats' => $deliveryStats,
            ],
            'quarantined' => [
                'title' => 'Quarantined',
                'summary' => 'Lead held for manual review',
                'reason' => $lead->reject_reason,
                'hints' => $hints,
                'delivery_stats' => $deliveryStats,
            ],
            default => [
                'title' => ucfirst($status),
                'summary' => 'Pipeline still in progress',
                'reason' => null,
                'hints' => $hints,
                'delivery_stats' => $deliveryStats,
            ],
        };
    }
}
