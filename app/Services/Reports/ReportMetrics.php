<?php

namespace App\Services\Reports;

use App\Services\Leads\LeadQualityService;
use App\Support\TenantFinancialSummary;
use App\Support\Tenancy\AccountContext;
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
        private readonly ?Carbon $until = null,
        private readonly ?int $campaignId = null,
        private readonly ?string $currency = null,
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request): self
    {
        $accountId = $request->attributes->get('account')?->id
            ?? AccountContext::id()
            ?? $request->user()?->account_id;

        $campaignId = $request->integer('campaign_id') ?: null;

        $currency = strtoupper(trim($request->string('currency')->toString()));
        $currency = strlen($currency) === 3 ? $currency : null;

        $month = $request->string('month')->toString();
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

            return new self(
                $accountId,
                $start->copy(),
                $start->daysInMonth,
                $start,
                $start->copy()->endOfMonth()->startOfDay(),
                $campaignId,
                $currency,
            );
        }

        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();

        if ($dateFrom && $dateTo
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $since = Carbon::createFromFormat('Y-m-d', $dateFrom)->startOfDay();
            $until = Carbon::createFromFormat('Y-m-d', $dateTo)->startOfDay();

            if ($until->lt($since)) {
                [$since, $until] = [$until, $since];
            }

            $days = min($since->diffInDays($until) + 1, 366);

            return new self(
                $accountId,
                $since,
                $days,
                null,
                $until,
                $campaignId,
                $currency,
            );
        }

        $days = (int) $request->input('days', 28);
        $days = in_array($days, [1, 7, 14, 28, 30, 60, 90], true) ? $days : 28;

        return new self(
            $accountId,
            today()->subDays($days - 1),
            $days,
            null,
            today(),
            $campaignId,
            $currency,
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

    public function until(): Carbon
    {
        return $this->until ?? today();
    }

    public function campaignId(): ?int
    {
        return $this->campaignId;
    }

    public function currency(): ?string
    {
        return $this->currency;
    }

    /**
     * @return list<Carbon>
     */
    private function chartDates(): array
    {
        $dates = [];
        $cursor = $this->since->copy();
        $end = $this->until();

        while ($cursor->lte($end)) {
            $dates[] = $cursor->copy();
            $cursor->addDay();
        }

        return $dates;
    }

    public function periodLabel(): string
    {
        if ($this->monthStart) {
            return $this->monthStart->format('F Y');
        }

        if ($this->since->isSameDay($this->until())) {
            return $this->since->format('j M Y');
        }

        return $this->since->format('j M').' – '.$this->until()->format('j M Y');
    }

    /**
     * @return array{labels: list<string>, dates: list<string>, leads: list<int>, sold: list<int>, rejected: list<int>, revenue: list<float>, payout: list<float>, margin: list<float>}
     */
    public function dailyCharts(): array
    {
        $labels = [];
        $dates = [];
        $leads = [];
        $sold = [];
        $rejected = [];
        $revenue = [];
        $payout = [];
        $margin = [];

        foreach ($this->chartDates() as $date) {
            $labels[] = $date->format('D j');
            $dates[] = $date->toDateString();
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

        return compact('labels', 'dates', 'leads', 'sold', 'rejected', 'revenue', 'payout', 'margin');
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
            ->whereDate('leads.received_at', '<=', $this->until())
            ->whereDate('leads.received_at', '<=', $this->until())
            ->count();

        $quarantined = (int) $this->leadsQuery()
            ->where('leads.status', 'quarantined')
            ->whereDate('leads.received_at', '>=', $this->since)
            ->whereDate('leads.received_at', '<=', $this->until())
            ->whereDate('leads.received_at', '<=', $this->until())
            ->count();

        $distributionOutcome = $this->distributionOutcome();
        $delivery = $this->deliveryHealth();
        $redirect = $this->redirectHealth();
        $quality = $this->leadQuality();

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
            'revenue_by_currency' => TenantFinancialSummary::totalsByCurrency(
                $this->accountId,
                $this->since,
                $this->until(),
                $this->campaignId,
                false,
                $this->currency,
            ),
            'conversion' => $conversion,
            'sell_through' => $sellThrough,
            'reject_rate' => $rejectRate,
            'outbid_total' => (int) ($distributionOutcome['outbid'] ?? 0),
            'ping_rejections' => (int) (($distributionOutcome['failed'] ?? 0) + ($distributionOutcome['skipped'] ?? 0)),
            'kpis' => $this->unitEconomics($received, $sold, $revenue, $payout, $margin),
            'kpis_by_currency' => $this->kpisByCurrency(),
            'delivery' => $delivery,
            'redirect' => $redirect,
            'quality' => $quality,
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
     * Per-currency unit economics when multiple currencies are in play (no currency filter).
     *
     * @return list<array<string, mixed>>
     */
    public function kpisByCurrency(): array
    {
        if ($this->currency) {
            return [];
        }

        $financialRows = TenantFinancialSummary::totalsByCurrency(
            $this->accountId,
            $this->since,
            $this->until(),
            $this->campaignId,
        );

        if (count($financialRows) <= 1) {
            return [];
        }

        $result = [];

        foreach ($financialRows as $row) {
            $currency = $row['currency'];
            $received = $this->receivedLeadsForCurrency($currency);
            $sold = $this->soldLeadsForCurrency($currency);
            $kpis = $this->unitEconomics($received, $sold, $row['revenue'], $row['payout'], $row['margin']);
            $payoutShare = $row['revenue'] > 0 ? round(($row['payout'] / $row['revenue']) * 100, 1) : 0.0;
            $netPerLead = $received > 0 ? round($row['margin'] / $received, 2) : 0.0;

            $result[] = array_merge($kpis, [
                'currency' => $currency,
                'received' => $received,
                'sold' => $sold,
                'payout_share_pct' => $payoutShare,
                'net_per_lead' => $netPerLead,
            ]);
        }

        return $result;
    }

    private function receivedLeadsForCurrency(string $currency): int
    {
        return (int) $this->leadsQuery()
            ->join('campaigns', 'campaigns.id', '=', 'leads.campaign_id')
            ->where('campaigns.currency', strtoupper($currency))
            ->whereDate('leads.received_at', '>=', $this->since)
            ->whereDate('leads.received_at', '<=', $this->until())
            ->count();
    }

    private function soldLeadsForCurrency(string $currency): int
    {
        return (int) $this->leadsQuery()
            ->join('lead_financials', 'lead_financials.lead_id', '=', 'leads.id')
            ->where('leads.status', 'sold')
            ->where('lead_financials.currency', strtoupper($currency))
            ->whereDate('leads.distributed_at', '>=', $this->since)
            ->whereDate('leads.distributed_at', '<=', $this->until())
            ->count();
    }

    /**
     * @return array<string, int|float>
     */
    public function leadQuality(): array
    {
        $totals = [
            'leads_scored' => 0,
            'score_sum' => 0,
            'excellent' => 0,
            'good' => 0,
            'fair' => 0,
            'poor' => 0,
            'email_checked' => 0,
            'email_passed' => 0,
            'email_failed' => 0,
            'hlr_checked' => 0,
            'hlr_passed' => 0,
            'hlr_failed' => 0,
            'ip_checked' => 0,
            'ip_passed' => 0,
            'ip_failed' => 0,
        ];

        $this->leadsQuery()
            ->whereDate('leads.received_at', '>=', $this->since)
            ->whereDate('leads.received_at', '<=', $this->until())
            ->select(['leads.id', 'leads.metadata', 'leads.field_data'])
            ->orderBy('leads.id')
            ->chunk(500, function ($rows) use (&$totals) {
                foreach ($rows as $row) {
                    $metadata = is_string($row->metadata)
                        ? json_decode($row->metadata, true) ?? []
                        : (array) json_decode(json_encode($row->metadata), true);
                    $fieldData = is_string($row->field_data)
                        ? json_decode($row->field_data, true) ?? []
                        : (array) json_decode(json_encode($row->field_data), true);

                    $analysis = LeadQualityService::analyze($metadata, $fieldData);
                    $totals['leads_scored']++;
                    $totals['score_sum'] += $analysis['score'];
                    $totals[$analysis['grade']] = ($totals[$analysis['grade']] ?? 0) + 1;

                    if ($analysis['email']['status'] !== 'unchecked') {
                        $totals['email_checked']++;
                        if ($analysis['email']['passed']) {
                            $totals['email_passed']++;
                        } else {
                            $totals['email_failed']++;
                        }
                    }

                    if ($analysis['hlr']['status'] !== 'unchecked') {
                        $totals['hlr_checked']++;
                        if ($analysis['hlr']['passed']) {
                            $totals['hlr_passed']++;
                        } else {
                            $totals['hlr_failed']++;
                        }
                    }

                    if ($analysis['ip']['status'] !== 'unchecked') {
                        $totals['ip_checked']++;
                        if ($analysis['ip']['passed']) {
                            $totals['ip_passed']++;
                        } else {
                            $totals['ip_failed']++;
                        }
                    }
                }
            });

        $scored = max(1, $totals['leads_scored']);

        return [
            'leads_scored' => $totals['leads_scored'],
            'avg_score' => $totals['leads_scored'] > 0 ? round($totals['score_sum'] / $totals['leads_scored'], 1) : 0.0,
            'excellent' => $totals['excellent'],
            'good' => $totals['good'],
            'fair' => $totals['fair'],
            'poor' => $totals['poor'],
            'excellent_rate' => round(($totals['excellent'] / $scored) * 100, 1),
            'email_checked' => $totals['email_checked'],
            'email_pass_rate' => $totals['email_checked'] > 0
                ? round(($totals['email_passed'] / $totals['email_checked']) * 100, 1)
                : 0.0,
            'email_failed' => $totals['email_failed'],
            'hlr_checked' => $totals['hlr_checked'],
            'hlr_pass_rate' => $totals['hlr_checked'] > 0
                ? round(($totals['hlr_passed'] / $totals['hlr_checked']) * 100, 1)
                : 0.0,
            'hlr_failed' => $totals['hlr_failed'],
            'ip_checked' => $totals['ip_checked'],
            'ip_pass_rate' => $totals['ip_checked'] > 0
                ? round(($totals['ip_passed'] / $totals['ip_checked']) * 100, 1)
                : 0.0,
            'ip_failed' => $totals['ip_failed'],
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function redirectHealth(): array
    {
        $row = $this->leadsQuery()
            ->where('leads.status', 'sold')
            ->whereDate('leads.distributed_at', '>=', $this->since)
            ->whereDate('leads.distributed_at', '<=', $this->until())
            ->selectRaw("
                sum(case when leads.redirect_offered_at is not null then 1 else 0 end) as offered,
                sum(case when leads.redirect_followed_at is not null then 1 else 0 end) as followed
            ")
            ->first();

        $offered = (int) ($row->offered ?? 0);
        $followed = (int) ($row->followed ?? 0);

        return [
            'offered' => $offered,
            'followed' => $followed,
            'redirect_rate' => $offered > 0 ? round(($followed / $offered) * 100, 1) : 0.0,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function deliveryHealth(): array
    {
        $row = $this->deliveryLogsQuery()
            ->whereDate('delivery_logs.created_at', '>=', $this->since)
            ->whereDate('delivery_logs.created_at', '<=', $this->until())
            ->whereDate('delivery_logs.created_at', '<=', $this->until())
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
            ->whereDate('delivery_logs.created_at', '<=', $this->until())
            ->whereDate('delivery_logs.created_at', '<=', $this->until())
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
            ->whereDate('leads.received_at', '<=', $this->until())
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
            ->whereDate('leads.distributed_at', '<=', $this->until())
            ->groupBy('buyers.id', 'buyers.name')
            ->selectRaw('
                buyers.id as buyer_id,
                buyers.name as name,
                count(*) as leads,
                sum(lead_financials.revenue) as revenue,
                sum(lead_financials.payout) as payout,
                sum(lead_financials.margin) as margin,
                sum(case when leads.redirect_offered_at is not null then 1 else 0 end) as redirects_offered,
                sum(case when leads.redirect_followed_at is not null then 1 else 0 end) as redirects_followed
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
            ->whereDate('leads.distributed_at', '<=', $this->until())
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
            ->whereDate('leads.received_at', '<=', $this->until())
            ->when($this->currency, fn ($q) => $q->where('campaigns.currency', $this->currency))
            ->groupBy('campaigns.id', 'campaigns.name', 'campaigns.reference', 'campaigns.currency')
            ->selectRaw("
                campaigns.id as campaign_id,
                campaigns.name as name,
                campaigns.reference as reference,
                campaigns.currency as currency,
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
            ->whereDate('leads.received_at', '<=', $this->until())
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
        $redirectStats = $this->redirectStatsSubquery('deliveries.id');

        return $this->deliveryLogsQuery()
            ->join('deliveries', 'deliveries.id', '=', 'delivery_logs.delivery_id')
            ->join('buyers', 'buyers.id', '=', 'deliveries.buyer_id')
            ->leftJoinSub($redirectStats, 'redirect_stats', 'redirect_stats.group_key', '=', 'deliveries.id')
            ->whereDate('delivery_logs.created_at', '>=', $this->since)
            ->whereDate('delivery_logs.created_at', '<=', $this->until())
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
                avg(delivery_logs.duration_ms) as avg_duration_ms,
                coalesce(max(redirect_stats.redirects_offered), 0) as redirects_offered,
                coalesce(max(redirect_stats.redirects_followed), 0) as redirects_followed
            ")
            ->orderBy('deliveries.tier')
            ->orderByDesc('attempts')
            ->paginate($perPage, ['*'], 'delivery_page')
            ->withQueryString();
    }

    public function tierSummary(int $perPage = 10): LengthAwarePaginator
    {
        $redirectStats = $this->redirectStatsSubquery('deliveries.tier');

        return $this->deliveryLogsQuery()
            ->join('deliveries', 'deliveries.id', '=', 'delivery_logs.delivery_id')
            ->leftJoinSub($redirectStats, 'redirect_stats', 'redirect_stats.group_key', '=', 'deliveries.tier')
            ->whereDate('delivery_logs.created_at', '>=', $this->since)
            ->whereDate('delivery_logs.created_at', '<=', $this->until())
            ->whereNotNull('deliveries.tier')
            ->groupBy('deliveries.tier')
            ->selectRaw("
                deliveries.tier as tier,
                count(*) as attempts,
                sum(case when delivery_logs.status = 'success' then 1 else 0 end) as wins,
                sum(case when delivery_logs.status = 'outbid' then 1 else 0 end) as outbid,
                sum(case when delivery_logs.status in ('failed','skipped') then 1 else 0 end) as rejections,
                sum(delivery_logs.revenue) as revenue,
                coalesce(max(redirect_stats.redirects_offered), 0) as redirects_offered,
                coalesce(max(redirect_stats.redirects_followed), 0) as redirects_followed
            ")
            ->orderBy('deliveries.tier')
            ->paginate($perPage, ['*'], 'tier_page')
            ->withQueryString();
    }

    private function redirectStatsSubquery(string $groupColumn): \Illuminate\Database\Query\Builder
    {
        return DB::table('leads')
            ->join('deliveries', 'deliveries.id', '=', 'leads.winning_delivery_id')
            ->when($this->accountId, fn ($q) => $q->where('leads.account_id', $this->accountId))
            ->when($this->campaignId, fn ($q) => $q->where('leads.campaign_id', $this->campaignId))
            ->where('leads.status', 'sold')
            ->whereDate('leads.distributed_at', '>=', $this->since)
            ->whereDate('leads.distributed_at', '<=', $this->until())
            ->groupBy($groupColumn)
            ->selectRaw("
                {$groupColumn} as group_key,
                sum(case when leads.redirect_offered_at is not null then 1 else 0 end) as redirects_offered,
                sum(case when leads.redirect_followed_at is not null then 1 else 0 end) as redirects_followed
            ");
    }

    private function leadsQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('leads')
            ->when($this->accountId, fn ($q) => $q->where('leads.account_id', $this->accountId))
            ->when($this->campaignId, fn ($q) => $q->where('leads.campaign_id', $this->campaignId));
    }

    private function deliveryLogsQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('delivery_logs')
            ->join('leads', 'leads.id', '=', 'delivery_logs.lead_id')
            ->when($this->accountId, fn ($q) => $q->where('leads.account_id', $this->accountId))
            ->when($this->campaignId, fn ($q) => $q->where('leads.campaign_id', $this->campaignId));
    }

    private function financialsQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('lead_financials')
            ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
            ->when($this->accountId, fn ($q) => $q->where('leads.account_id', $this->accountId))
            ->when($this->campaignId, fn ($q) => $q->where('leads.campaign_id', $this->campaignId))
            ->when($this->currency, fn ($q) => $q->where('lead_financials.currency', $this->currency));
    }

    /**
     * @return array{revenue: float, payout: float, margin: float}
     */
    private function financialsForDate(Carbon $date): array
    {
        $row = $this->financialsQuery()
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
