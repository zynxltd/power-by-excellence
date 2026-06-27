<?php

namespace App\Services\Calls;

use App\Enums\CallStatus;
use App\Models\CallDeliveryLog;
use App\Models\CallSession;
use App\Models\Campaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CallAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(?int $accountId = null, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subDays(30);
        $to = $to ?? now();

        $query = CallSession::withoutGlobalScopes()
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->whereBetween('created_at', [$from, $to]);

        $total = (clone $query)->count();
        $connected = (clone $query)->whereIn('status', [CallStatus::Connected, CallStatus::Completed])->count();
        $sold = (clone $query)->whereNotNull('sold_to_buyer_id')->count();
        $revenue = (clone $query)->whereNotNull('revenue')->sum('revenue');
        $avgDuration = (clone $query)->where('duration_seconds', '>', 0)->avg('duration_seconds');

        return [
            'total_calls' => $total,
            'connected_calls' => $connected,
            'sold_calls' => $sold,
            'connect_rate' => $total > 0 ? round($connected / $total * 100, 2) : 0,
            'conversion_rate' => $total > 0 ? round($sold / $total * 100, 2) : 0,
            'revenue' => round((float) $revenue, 2),
            'avg_duration_seconds' => round((float) $avgDuration, 1),
            'avg_revenue_per_call' => $sold > 0 ? round((float) $revenue / $sold, 2) : 0,
        ];
    }

    /**
     * Traffic Flow: buyer first-look percentage in call ping tree.
     *
     * @return list<array<string, mixed>>
     */
    public function trafficFlow(?int $campaignId = null, ?int $accountId = null, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subDays(30);
        $to = $to ?? now();

        $query = CallDeliveryLog::query()
            ->select([
                'buyer_id',
                DB::raw('COUNT(*) as total_pings'),
                DB::raw('SUM(CASE WHEN status = \'accepted\' THEN 1 ELSE 0 END) as accepted_pings'),
                DB::raw('SUM(CASE WHEN tier = 1 THEN 1 ELSE 0 END) as tier_one_pings'),
            ])
            ->whereHas('callSession', function ($q) use ($campaignId, $accountId, $from, $to) {
                $q->whereBetween('created_at', [$from, $to]);
                if ($campaignId) {
                    $q->where('campaign_id', $campaignId);
                }
                if ($accountId) {
                    $q->where('account_id', $accountId);
                }
            })
            ->whereNotNull('buyer_id')
            ->groupBy('buyer_id')
            ->with('buyer:id,name,reference');

        return $query->get()->map(function ($row) {
            $total = (int) $row->total_pings;
            $tierOne = (int) $row->tier_one_pings;

            return [
                'buyer_id' => $row->buyer_id,
                'buyer_name' => $row->buyer?->name,
                'buyer_reference' => $row->buyer?->reference,
                'total_pings' => $total,
                'accepted_pings' => (int) $row->accepted_pings,
                'accept_rate' => $total > 0 ? round($row->accepted_pings / $total * 100, 2) : 0,
                'first_look_pct' => $total > 0 ? round($tierOne / $total * 100, 2) : 0,
            ];
        })->sortByDesc('first_look_pct')->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function byCampaign(?int $accountId = null, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subDays(30);
        $to = $to ?? now();

        return CallSession::withoutGlobalScopes()
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->whereBetween('created_at', [$from, $to])
            ->select([
                'campaign_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN sold_to_buyer_id IS NOT NULL THEN 1 ELSE 0 END) as sold'),
                DB::raw('SUM(revenue) as revenue'),
            ])
            ->groupBy('campaign_id')
            ->with('campaign:id,name,reference')
            ->get()
            ->map(fn ($row) => [
                'campaign_id' => $row->campaign_id,
                'campaign_name' => $row->campaign?->name,
                'total' => (int) $row->total,
                'sold' => (int) $row->sold,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }
}
