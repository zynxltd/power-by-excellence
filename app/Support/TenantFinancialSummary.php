<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenantFinancialSummary
{
    /**
     * @return list<array{currency: string, revenue: float, payout: float, margin: float}>
     */
    public static function totalsByCurrency(
        ?int $accountId = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $campaignId = null,
        bool $todayOnly = false,
        ?string $currency = null,
    ): array {
        $rows = DB::table('lead_financials')
            ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
            ->when($accountId, fn ($q) => $q->where('leads.account_id', $accountId))
            ->when($campaignId, fn ($q) => $q->where('leads.campaign_id', $campaignId))
            ->when($currency, fn ($q) => $q->where('lead_financials.currency', strtoupper($currency)))
            ->when($todayOnly, fn ($q) => $q->whereDate('leads.distributed_at', today()))
            ->when($from && ! $todayOnly, fn ($q) => $q->whereDate('leads.distributed_at', '>=', $from))
            ->when($to && ! $todayOnly, fn ($q) => $q->whereDate('leads.distributed_at', '<=', $to))
            ->groupBy('lead_financials.currency')
            ->selectRaw('
                lead_financials.currency as currency,
                coalesce(sum(lead_financials.revenue), 0) as revenue,
                coalesce(sum(lead_financials.payout), 0) as payout,
                coalesce(sum(lead_financials.margin), 0) as margin
            ')
            ->orderByDesc('revenue')
            ->get();

        return $rows->map(fn ($row) => [
            'currency' => strtoupper((string) $row->currency),
            'revenue' => (float) $row->revenue,
            'payout' => (float) $row->payout,
            'margin' => (float) $row->margin,
        ])->values()->all();
    }

    /**
     * @return list<string>
     */
    public static function currenciesInUse(?int $accountId): array
    {
        $fromFinancials = DB::table('lead_financials')
            ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
            ->when($accountId, fn ($q) => $q->where('leads.account_id', $accountId))
            ->distinct()
            ->pluck('lead_financials.currency');

        $fromCampaigns = DB::table('campaigns')
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->distinct()
            ->pluck('currency');

        return Collection::make($fromFinancials)
            ->merge($fromCampaigns)
            ->map(fn ($c) => strtoupper((string) $c))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
