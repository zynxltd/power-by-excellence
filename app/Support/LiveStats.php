<?php

namespace App\Support;

use App\Enums\LeadStatus;
use App\Models\DeliveryLog;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;

class LiveStats
{
    /**
     * @return array<string, mixed>
     */
    public static function snapshot(?int $campaignId = null): array
    {
        $base = Lead::query();
        if ($campaignId) {
            $base->where('campaign_id', $campaignId);
        }

        $leadsToday = (clone $base)->whereDate('received_at', today())->count();
        $rejectedToday = (clone $base)
            ->whereIn('status', [LeadStatus::Rejected->value, LeadStatus::Duplicate->value])
            ->whereDate('received_at', today())
            ->count();
        $processingCount = (clone $base)->whereIn('status', LeadStatus::processingValues())->count();

        $rawQueueCounts = (clone $base)
            ->selectRaw('status, count(*) as count')
            ->whereIn('status', [
                LeadStatus::Pending->value,
                LeadStatus::Validating->value,
                LeadStatus::Accepted->value,
                LeadStatus::Distributing->value,
                LeadStatus::Quarantined->value,
            ])
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $queueBreakdown = LeadQueueMetrics::queueBreakdown($rawQueueCounts);

        $processingLeads = Lead::query()
            ->when($campaignId, fn (Builder $q) => $q->where('campaign_id', $campaignId))
            ->whereIn('status', LeadStatus::processingValues())
            ->with(['campaign:id,name'])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get(['id', 'uuid', 'status', 'campaign_id', 'received_at', 'updated_at'])
            ->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'uuid' => $lead->uuid,
                'campaign' => $lead->campaign?->name,
                'received_at' => $lead->received_at?->toIso8601String(),
                'updated_at' => $lead->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'leads_today' => $leadsToday,
            'sold_today' => (clone $base)->where('status', 'sold')->whereDate('distributed_at', today())->count(),
            'unsold_today' => (clone $base)->where('status', 'unsold')->whereDate('received_at', today())->count(),
            'rejected_today' => $rejectedToday,
            'pending' => (clone $base)->whereIn('status', LeadStatus::inFlightValues())->count(),
            'processing_count' => $processingCount,
            'quarantined' => (clone $base)->where('status', 'quarantined')->count(),
            'ping_posts_today' => DeliveryLog::query()->forCurrentAccount()
                ->when($campaignId, fn ($q) => $q->whereHas('lead', fn ($lq) => $lq->where('campaign_id', $campaignId)))
                ->whereDate('created_at', today())
                ->whereNotNull('ping_request')
                ->count(),
            'failed_today' => DeliveryLog::query()->forCurrentAccount()
                ->when($campaignId, fn ($q) => $q->whereHas('lead', fn ($lq) => $lq->where('campaign_id', $campaignId)))
                ->whereDate('created_at', today())
                ->whereIn('status', ['failed', 'skipped'])
                ->count(),
            'revenue_today' => (float) Lead::query()
                ->join('lead_financials', 'leads.id', '=', 'lead_financials.lead_id')
                ->when($campaignId, fn ($q) => $q->where('leads.campaign_id', $campaignId))
                ->whereDate('leads.distributed_at', today())
                ->sum('lead_financials.revenue'),
            'reject_rate' => $leadsToday > 0 ? round(($rejectedToday / $leadsToday) * 100, 1) : 0.0,
            'queue_breakdown' => $queueBreakdown,
            'pipeline_summary' => self::pipelineSummary($campaignId),
            'processing_leads' => $processingLeads,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function pipelineSummary(?int $campaignId = null): array
    {
        $query = Lead::query();
        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }

        $counts = (clone $query)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        return LeadQueueMetrics::pipelineSummary($counts, (int) (clone $query)->count());
    }
}
