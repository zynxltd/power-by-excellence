<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReportMetrics
{
    public function __construct(
        private readonly ?int $accountId,
        private readonly Carbon $since,
        private readonly int $days,
        private readonly ?Carbon $monthStart = null,
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request): self
    {
        $month = $request->string('month')->toString();
        $days = (int) $request->input('days', 28);
        $days = in_array($days, [7, 14, 28, 30], true) ? $days : 28;

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

            return new self(
                $request->attributes->get('account')?->id ?? $request->user()?->account_id,
                $start->copy(),
                $start->daysInMonth,
                $start,
            );
        }

        return new self(
            $request->attributes->get('account')?->id ?? $request->user()?->account_id,
            today()->subDays($days),
            $days,
            null,
        );
    }

    public function days(): int
    {
        return $this->days;
    }

    public function month(): ?string
    {
        return $this->monthStart?->format('Y-m');
    }

    public function since(): Carbon
    {
        return $this->since;
    }

    /**
     * @return array{labels: list<string>, leads: list<int>, sold: list<int>, rejected: list<int>, revenue: list<float>, payout: list<float>, margin: list<float>}
     */
    public function dailyCharts(): array
    {
        $labels = [];
        $leads = [];
        $sold = [];
        $rejected = [];
        $revenue = [];
        $payout = [];
        $margin = [];

        for ($i = $this->days - 1; $i >= 0; $i--) {
            $date = $this->monthStart
                ? $this->monthStart->copy()->addDays($this->days - 1 - $i)
                : today()->subDays($i);

            $labels[] = $date->format('D j');
            $leads[] = (int) $this->leadsQuery()->whereDate('leads.received_at', $date)->count();
            $sold[] = (int) $this->leadsQuery()
                ->whereDate('leads.distributed_at', $date)
                ->where('leads.status', 'sold')
                ->count();
            $rejected[] = (int) $this->leadsQuery()
                ->whereDate('leads.received_at', $date)
                ->where('leads.status', 'rejected')
                ->count();

            $financials = $this->financialsForDate($date);
            $revenue[] = $financials['revenue'];
            $payout[] = $financials['payout'];
            $margin[] = $financials['margin'];
        }

        return compact('labels', 'leads', 'sold', 'rejected', 'revenue', 'payout', 'margin');
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(array $charts): array
    {
        $received = (int) array_sum($charts['leads']);
        $sold = (int) array_sum($charts['sold']);
        $rejected = (int) array_sum($charts['rejected']);
        $revenue = (float) array_sum($charts['revenue']);
        $payout = (float) array_sum($charts['payout']);
        $margin = (float) array_sum($charts['margin']);

        $unsold = (int) $this->leadsQuery()
            ->where('leads.status', 'unsold')
            ->whereDate('leads.received_at', '>=', $this->since)
            ->count();

        $quarantined = (int) $this->leadsQuery()
            ->where('leads.status', 'quarantined')
            ->whereDate('leads.received_at', '>=', $this->since)
            ->count();

        $distributionOutcome = $this->distributionOutcome();
        $delivery = $this->deliveryHealth();

        $distributed = $sold + $unsold;
        $conversion = $received > 0 ? round(($sold / $received) * 100, 1) : 0.0;
        $sellThrough = $distributed > 0 ? round(($sold / $distributed) * 100, 1) : 0.0;
        $rejectRate = $received > 0 ? round(($rejected / $received) * 100, 1) : 0.0;

        return [
            'leads_period' => $received,
            'sold_period' => $sold,
            'unsold_period' => $unsold,
            'rejected_period' => $rejected,
            'quarantined_period' => $quarantined,
            'revenue_period' => $revenue,
            'payout_period' => $payout,
            'margin_period' => $margin,
            'conversion' => $conversion,
            'sell_through' => $sellThrough,
            'reject_rate' => $rejectRate,
            'outbid_total' => (int) ($distributionOutcome['outbid'] ?? 0),
            'ping_rejections' => (int) (($distributionOutcome['failed'] ?? 0) + ($distributionOutcome['skipped'] ?? 0)),
            'kpis' => $this->unitEconomics($received, $sold, $revenue, $payout, $margin),
            'delivery' => $delivery,
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function unitEconomics(int $received, int $sold, float $revenue, float $payout, float $margin): array
    {
        $marginPct = $revenue > 0 ? round(($margin / $revenue) * 100, 1) : 0.0;

        return [
            'epl' => $sold > 0 ? round($revenue / $sold, 2) : 0.0,
            'epc' => $received > 0 ? round($revenue / $received, 2) : 0.0,
            'cpa' => $sold > 0 ? round($payout / $sold, 2) : 0.0,
            'cpl' => $received > 0 ? round($payout / $received, 2) : 0.0,
            'mpl' => $sold > 0 ? round($margin / $sold, 2) : 0.0,
            'margin_pct' => $marginPct,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function deliveryHealth(): array
    {
        $row = $this->deliveryLogsQuery()
            ->whereDate('delivery_logs.created_at', '>=', $this->since)
            ->selectRaw("
                count(*) as attempts,
                sum(case when delivery_logs.status = 'success' then 1 else 0 end) as successes,
                sum(case when delivery_logs.status = 'outbid' then 1 else 0 end) as outbid,
                sum(case when delivery_logs.status in ('failed','skipped') then 1 else 0 end) as rejections,
                avg(delivery_logs.duration_ms) as avg_duration_ms,
                sum(delivery_logs.revenue) as revenue
            ")
            ->first();

        $attempts = (int) ($row->attempts ?? 0);
        $successes = (int) ($row->successes ?? 0);
        $outbid = (int) ($row->outbid ?? 0);

        return [
            'attempts' => $attempts,
            'successes' => $successes,
            'outbid' => $outbid,
            'rejections' => (int) ($row->rejections ?? 0),
            'success_rate' => $attempts > 0 ? round(($successes / $attempts) * 100, 1) : 0.0,
            'outbid_rate' => $attempts > 0 ? round(($outbid / $attempts) * 100, 1) : 0.0,
            'avg_duration_ms' => $row->avg_duration_ms ? (int) round($row->avg_duration_ms) : 0,
            'revenue' => (float) ($row->revenue ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function distributionOutcome(): array
    {
        return $this->deliveryLogsQuery()
            ->whereDate('delivery_logs.created_at', '>=', $this->since)
            ->selectRaw('delivery_logs.status, count(*) as total')
            ->groupBy('delivery_logs.status')
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function leadStatusBreakdown(): array
    {
        return $this->leadsQuery()
            ->whereDate('leads.received_at', '>=', $this->since)
            ->selectRaw('leads.status, count(*) as total')
            ->groupBy('leads.status')
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function byBuyer(int $perPage = 10): LengthAwarePaginator
    {
        return $this->leadsQuery()
            ->join('buyers', 'buyers.id', '=', 'leads.sold_to_buyer_id')
            ->join('lead_financials', 'lead_financials.lead_id', '=', 'leads.id')
            ->where('leads.status', 'sold')
            ->whereDate('leads.distributed_at', '>=', $this->since)
            ->groupBy('buyers.id', 'buyers.name')
            ->selectRaw('
                buyers.id as buyer_id,
                buyers.name as name,
                count(*) as leads,
                sum(lead_financials.revenue) as revenue,
                sum(lead_financials.payout) as payout,
                sum(lead_financials.margin) as margin
            ')
            ->orderByDesc('revenue')
            ->paginate($perPage, ['*'], 'buyer_page')
            ->withQueryString();
    }

    public function bySupplier(int $perPage = 10): LengthAwarePaginator
    {
        return $this->leadsQuery()
            ->join('suppliers', 'suppliers.id', '=', 'leads.supplier_id')
            ->join('lead_financials', 'lead_financials.lead_id', '=', 'leads.id')
            ->where('leads.status', 'sold')
            ->whereDate('leads.distributed_at', '>=', $this->since)
            ->groupBy('suppliers.id', 'suppliers.name')
            ->selectRaw('
                suppliers.id as supplier_id,
                suppliers.name as name,
                count(*) as leads,
                sum(lead_financials.revenue) as revenue,
                sum(lead_financials.payout) as payout,
                sum(lead_financials.margin) as margin
            ')
            ->orderByDesc('payout')
            ->paginate($perPage, ['*'], 'supplier_page')
            ->withQueryString();
    }

    public function byCampaign(int $perPage = 10): LengthAwarePaginator
    {
        return $this->leadsQuery()
            ->join('campaigns', 'campaigns.id', '=', 'leads.campaign_id')
            ->leftJoin('lead_financials', 'lead_financials.lead_id', '=', 'leads.id')
            ->whereDate('leads.received_at', '>=', $this->since)
            ->groupBy('campaigns.id', 'campaigns.name', 'campaigns.reference')
            ->selectRaw("
                campaigns.id as campaign_id,
                campaigns.name as name,
                campaigns.reference as reference,
                count(*) as received,
                sum(case when leads.status = 'sold' then 1 else 0 end) as sold,
                sum(case when leads.status = 'unsold' then 1 else 0 end) as unsold,
                sum(case when leads.status = 'rejected' then 1 else 0 end) as rejected,
                coalesce(sum(lead_financials.revenue), 0) as revenue,
                coalesce(sum(lead_financials.payout), 0) as payout,
                coalesce(sum(lead_financials.margin), 0) as margin
            ")
            ->orderByDesc('revenue')
            ->paginate($perPage, ['*'], 'campaign_page')
            ->withQueryString();
    }

    public function bySid(int $perPage = 15): LengthAwarePaginator
    {
        return $this->leadsQuery()
            ->leftJoin('suppliers', 'suppliers.id', '=', 'leads.supplier_id')
            ->leftJoin('lead_financials', 'lead_financials.lead_id', '=', 'leads.id')
            ->whereDate('leads.received_at', '>=', $this->since)
            ->whereNotNull('leads.sid')
            ->where('leads.sid', '!=', '')
            ->groupBy('leads.sid', 'leads.supplier_id', 'suppliers.name')
            ->selectRaw("
                leads.sid as sid,
                leads.supplier_id as supplier_id,
                suppliers.name as supplier_name,
                count(*) as received,
                sum(case when leads.status = 'sold' then 1 else 0 end) as sold,
                sum(case when leads.status = 'rejected' then 1 else 0 end) as rejected,
                coalesce(sum(lead_financials.revenue), 0) as revenue,
                coalesce(sum(lead_financials.payout), 0) as payout,
                coalesce(sum(lead_financials.margin), 0) as margin
            ")
            ->orderByDesc('revenue')
            ->paginate($perPage, ['*'], 'sid_page')
            ->withQueryString();
    }

    public function deliveryPerformance(int $perPage = 15): LengthAwarePaginator
    {
        return $this->deliveryLogsQuery()
            ->join('deliveries', 'deliveries.id', '=', 'delivery_logs.delivery_id')
            ->join('buyers', 'buyers.id', '=', 'deliveries.buyer_id')
            ->whereDate('delivery_logs.created_at', '>=', $this->since)
            ->groupBy('deliveries.id', 'deliveries.name', 'deliveries.method', 'deliveries.tier', 'deliveries.campaign_id', 'buyers.name')
            ->selectRaw("
                deliveries.id as delivery_id,
                deliveries.campaign_id as campaign_id,
                deliveries.name as name,
                deliveries.method as method,
                deliveries.tier as tier,
                buyers.name as buyer_name,
                count(*) as attempts,
                sum(case when delivery_logs.status = 'success' then 1 else 0 end) as successes,
                sum(case when delivery_logs.status = 'outbid' then 1 else 0 end) as outbid,
                sum(case when delivery_logs.status in ('failed','skipped') then 1 else 0 end) as rejections,
                sum(delivery_logs.revenue) as revenue,
                avg(delivery_logs.duration_ms) as avg_duration_ms
            ")
            ->orderBy('deliveries.tier')
            ->orderByDesc('attempts')
            ->paginate($perPage, ['*'], 'delivery_page')
            ->withQueryString();
    }

    public function tierSummary(int $perPage = 10): LengthAwarePaginator
    {
        return $this->deliveryLogsQuery()
            ->join('deliveries', 'deliveries.id', '=', 'delivery_logs.delivery_id')
            ->whereDate('delivery_logs.created_at', '>=', $this->since)
            ->whereNotNull('deliveries.tier')
            ->groupBy('deliveries.tier')
            ->selectRaw("
                deliveries.tier as tier,
                count(*) as attempts,
                sum(case when delivery_logs.status = 'success' then 1 else 0 end) as wins,
                sum(case when delivery_logs.status = 'outbid' then 1 else 0 end) as outbid,
                sum(case when delivery_logs.status in ('failed','skipped') then 1 else 0 end) as rejections,
                sum(delivery_logs.revenue) as revenue
            ")
            ->orderBy('deliveries.tier')
            ->paginate($perPage, ['*'], 'tier_page')
            ->withQueryString();
    }

    private function leadsQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('leads')
            ->when($this->accountId, fn ($q) => $q->where('leads.account_id', $this->accountId));
    }

    private function deliveryLogsQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('delivery_logs')
            ->join('leads', 'leads.id', '=', 'delivery_logs.lead_id')
            ->when($this->accountId, fn ($q) => $q->where('leads.account_id', $this->accountId));
    }

    /**
     * @return array{revenue: float, payout: float, margin: float}
     */
    private function financialsForDate(Carbon $date): array
    {
        $row = DB::table('lead_financials')
            ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
            ->when($this->accountId, fn ($q) => $q->where('leads.account_id', $this->accountId))
            ->whereDate('leads.distributed_at', $date)
            ->selectRaw('
                coalesce(sum(lead_financials.revenue), 0) as revenue,
                coalesce(sum(lead_financials.payout), 0) as payout,
                coalesce(sum(lead_financials.margin), 0) as margin
            ')
            ->first();

        return [
            'revenue' => (float) ($row->revenue ?? 0),
            'payout' => (float) ($row->payout ?? 0),
            'margin' => (float) ($row->margin ?? 0),
        ];
    }
}
