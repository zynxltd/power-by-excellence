<?php

namespace App\Support;

use App\Models\DeliveryLog;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
        $rejectedToday = (clone $base)->where('status', 'rejected')->whereDate('received_at', today())->count();
        $processingCount = (clone $base)->where('status', 'processing')->count();

        $queueBreakdown = (clone $base)
            ->selectRaw('status, count(*) as count')
            ->whereIn('status', ['pending', 'processing', 'accepted', 'quarantined'])
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $processingLeads = Lead::query()
            ->when($campaignId, fn (Builder $q) => $q->where('campaign_id', $campaignId))
            ->where('status', 'processing')
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
            'pending' => (clone $base)->whereIn('status', ['pending', 'processing'])->count(),
            'processing_count' => $processingCount,
            'quarantined' => (clone $base)->where('status', 'quarantined')->count(),
            'ping_posts_today' => DeliveryLog::whereDate('created_at', today())
                ->when($campaignId, fn (Builder $q) => $q->whereHas('lead', fn (Builder $lq) => $lq->where('campaign_id', $campaignId)))
                ->whereNotNull('ping_request')
                ->count(),
            'failed_today' => DeliveryLog::whereDate('created_at', today())
                ->when($campaignId, fn (Builder $q) => $q->whereHas('lead', fn (Builder $lq) => $lq->where('campaign_id', $campaignId)))
                ->whereIn('status', ['failed', 'skipped'])
                ->count(),
            'revenue_today' => (float) DB::table('lead_financials')
                ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
                ->when($campaignId, fn ($q) => $q->where('leads.campaign_id', $campaignId))
                ->whereDate('leads.distributed_at', today())
                ->sum('lead_financials.revenue'),
            'reject_rate' => $leadsToday > 0 ? round(($rejectedToday / $leadsToday) * 100, 1) : 0.0,
            'queue_breakdown' => [
                'pending' => (int) ($queueBreakdown['pending'] ?? 0),
                'processing' => (int) ($queueBreakdown['processing'] ?? 0),
                'accepted' => (int) ($queueBreakdown['accepted'] ?? 0),
                'quarantined' => (int) ($queueBreakdown['quarantined'] ?? 0),
            ],
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
            ->pluck('count', 'status');

        return [
            'total' => (int) (clone $query)->count(),
            'pending' => (int) ($counts['pending'] ?? 0),
            'processing' => (int) ($counts['processing'] ?? 0),
            'sold' => (int) ($counts['sold'] ?? 0),
            'unsold' => (int) ($counts['unsold'] ?? 0),
            'quarantined' => (int) ($counts['quarantined'] ?? 0),
            'rejected' => (int) ($counts['rejected'] ?? 0),
        ];
    }
}
