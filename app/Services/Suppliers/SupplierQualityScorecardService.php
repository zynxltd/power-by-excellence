<?php

namespace App\Services\Suppliers;

use App\Models\Account;
use App\Models\Lead;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SupplierQualityScorecardService
{
    /**
     * @return array<string, mixed>
     */
    public function scorecard(Supplier $supplier, int $days = 30, ?Account $account = null): array
    {
        $days = $this->normalizeDays($days);
        [$from, $to] = $this->dateRange($days);
        $counts = $this->statusCounts($supplier->id, $from, $to);
        $submitted = $counts['submitted'];
        $badOutcomes = $counts['rejected'] + $counts['quarantined'] + $counts['duplicate'];
        $sold = $counts['sold'];

        $totalPayout = $this->sumPayoutForReceivedRange($supplier->id, $from, $to);
        $totalRevenue = $this->sumRevenueForSoldInRange($supplier->id, $from, $to);

        $rejectRate = $submitted > 0 ? round(($badOutcomes / $submitted) * 100, 1) : null;
        $soldRate = $submitted > 0 ? round(($sold / $submitted) * 100, 1) : null;
        $epl = $submitted > 0 ? round($totalPayout / $submitted, 2) : null;
        $revenuePerSold = $sold > 0 ? round($totalRevenue / $sold, 2) : null;

        $thresholds = $this->thresholdsFor($account ?? $supplier->account);
        $warnings = $this->warnings($rejectRate, $epl, $thresholds, $submitted);

        return [
            'days' => $days,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'submitted' => $submitted,
            'sold' => $sold,
            'rejected' => $counts['rejected'],
            'quarantined' => $counts['quarantined'],
            'duplicate' => $counts['duplicate'],
            'reject_rate_pct' => $rejectRate,
            'sold_rate_pct' => $soldRate,
            'total_supplier_payout' => round($totalPayout, 2),
            'total_buyer_revenue' => round($totalRevenue, 2),
            'epl' => $epl,
            'revenue_per_sold' => $revenuePerSold,
            'quality_grade' => $this->qualityGrade($submitted, $rejectRate, $epl, $thresholds),
            'warnings' => $warnings,
            'thresholds' => $thresholds,
            'sparkline' => $this->sparkline($supplier->id, 7),
        ];
    }

    /**
     * @param  list<int>  $supplierIds
     * @return array<int, array{reject_rate_pct: ?float, quality_grade: ?string, submitted: int}>
     */
    public function indexSummaries(array $supplierIds, int $days = 30, ?Account $account = null): array
    {
        if ($supplierIds === []) {
            return [];
        }

        $days = $this->normalizeDays($days);
        [$from, $to] = $this->dateRange($days);
        $thresholds = $this->thresholdsFor($account);

        $rows = Lead::query()
            ->whereIn('supplier_id', $supplierIds)
            ->whereBetween('received_at', [$from, $to])
            ->selectRaw('supplier_id')
            ->selectRaw('COUNT(*) as submitted')
            ->selectRaw("SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected")
            ->selectRaw("SUM(CASE WHEN status = 'quarantined' THEN 1 ELSE 0 END) as quarantined")
            ->selectRaw("SUM(CASE WHEN status = 'duplicate' THEN 1 ELSE 0 END) as duplicate")
            ->groupBy('supplier_id')
            ->get()
            ->keyBy('supplier_id');

        $payouts = DB::table('lead_financials')
            ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
            ->whereIn('leads.supplier_id', $supplierIds)
            ->whereBetween('leads.received_at', [$from, $to])
            ->selectRaw('leads.supplier_id, SUM(lead_financials.payout) as payout')
            ->groupBy('leads.supplier_id')
            ->pluck('payout', 'supplier_id');

        $summaries = [];
        foreach ($supplierIds as $supplierId) {
            $row = $rows->get($supplierId);
            $submitted = (int) ($row->submitted ?? 0);
            if ($submitted === 0) {
                $summaries[$supplierId] = [
                    'submitted' => 0,
                    'reject_rate_pct' => null,
                    'quality_grade' => null,
                ];

                continue;
            }

            $badOutcomes = (int) ($row->rejected ?? 0) + (int) ($row->quarantined ?? 0) + (int) ($row->duplicate ?? 0);
            $rejectRate = round(($badOutcomes / $submitted) * 100, 1);
            $epl = round(((float) ($payouts[$supplierId] ?? 0)) / $submitted, 2);

            $summaries[$supplierId] = [
                'submitted' => $submitted,
                'reject_rate_pct' => $rejectRate,
                'quality_grade' => $this->qualityGrade($submitted, $rejectRate, $epl, $thresholds),
            ];
        }

        return $summaries;
    }

    /**
     * @return array{labels: list<string>, submitted: list<int>, sold: list<int>, reject_rate_pct: list<?float>}
     */
    public function sparkline(int $supplierId, int $days = 7): array
    {
        $days = max(1, min($days, 90));
        $labels = [];
        $submitted = [];
        $sold = [];
        $rejectRatePct = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $labels[] = $date->format('D');
            $counts = $this->statusCounts(
                $supplierId,
                $date->copy()->startOfDay(),
                $date->copy()->endOfDay(),
            );
            $submitted[] = $counts['submitted'];
            $sold[] = $counts['sold'];
            $bad = $counts['rejected'] + $counts['quarantined'] + $counts['duplicate'];
            $rejectRatePct[] = $counts['submitted'] > 0
                ? round(($bad / $counts['submitted']) * 100, 1)
                : null;
        }

        return [
            'labels' => $labels,
            'submitted' => $submitted,
            'sold' => $sold,
            'reject_rate_pct' => $rejectRatePct,
        ];
    }

    /**
     * @return array{reject_rate_warn_pct: float, min_epl: float}
     */
    public function thresholdsFor(?Account $account): array
    {
        $stored = $account?->settings['supplier_quality_thresholds'] ?? [];

        return [
            'reject_rate_warn_pct' => (float) ($stored['reject_rate_warn_pct'] ?? 25),
            'min_epl' => (float) ($stored['min_epl'] ?? 1.0),
        ];
    }

    /**
     * @return list<string>
     */
    public function warnings(?float $rejectRate, ?float $epl, array $thresholds, int $submitted): array
    {
        if ($submitted === 0) {
            return [];
        }

        $messages = [];

        if ($rejectRate !== null && $rejectRate > $thresholds['reject_rate_warn_pct']) {
            $messages[] = sprintf(
                'Reject rate %.1f%% exceeds warn threshold %.1f%%.',
                $rejectRate,
                $thresholds['reject_rate_warn_pct'],
            );
        }

        if ($epl !== null && $epl < $thresholds['min_epl']) {
            $messages[] = sprintf(
                'EPL £%.2f is below flag threshold £%.2f.',
                $epl,
                $thresholds['min_epl'],
            );
        }

        return $messages;
    }

    public function qualityGrade(int $submitted, ?float $rejectRate, ?float $epl, array $thresholds): ?string
    {
        if ($submitted < 1) {
            return null;
        }

        $warnReject = $thresholds['reject_rate_warn_pct'];
        $minEpl = $thresholds['min_epl'];

        $rejectScore = 5;
        if ($rejectRate !== null) {
            if ($rejectRate > $warnReject * 2) {
                $rejectScore = 1;
            } elseif ($rejectRate > $warnReject * 1.5) {
                $rejectScore = 2;
            } elseif ($rejectRate > $warnReject) {
                $rejectScore = 3;
            } elseif ($rejectRate > $warnReject / 2) {
                $rejectScore = 4;
            }
        }

        $eplScore = 5;
        if ($epl !== null && $minEpl > 0) {
            if ($epl < $minEpl * 0.25) {
                $eplScore = 1;
            } elseif ($epl < $minEpl * 0.5) {
                $eplScore = 2;
            } elseif ($epl < $minEpl) {
                $eplScore = 3;
            } elseif ($epl < $minEpl * 1.5) {
                $eplScore = 4;
            }
        }

        $combined = (int) round(($rejectScore + $eplScore) / 2);

        return match ($combined) {
            5 => 'A',
            4 => 'B',
            3 => 'C',
            2 => 'D',
            default => 'F',
        };
    }

    /**
     * @return array{submitted: int, sold: int, rejected: int, quarantined: int, duplicate: int}
     */
    protected function statusCounts(int $supplierId, Carbon $from, Carbon $to): array
    {
        $row = Lead::query()
            ->where('supplier_id', $supplierId)
            ->whereBetween('received_at', [$from, $to])
            ->selectRaw('COUNT(*) as submitted')
            ->selectRaw("SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected")
            ->selectRaw("SUM(CASE WHEN status = 'quarantined' THEN 1 ELSE 0 END) as quarantined")
            ->selectRaw("SUM(CASE WHEN status = 'duplicate' THEN 1 ELSE 0 END) as duplicate")
            ->first();

        return [
            'submitted' => (int) ($row->submitted ?? 0),
            'sold' => (int) ($row->sold ?? 0),
            'rejected' => (int) ($row->rejected ?? 0),
            'quarantined' => (int) ($row->quarantined ?? 0),
            'duplicate' => (int) ($row->duplicate ?? 0),
        ];
    }

    protected function sumPayoutForReceivedRange(int $supplierId, Carbon $from, Carbon $to): float
    {
        return (float) DB::table('lead_financials')
            ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
            ->where('leads.supplier_id', $supplierId)
            ->whereBetween('leads.received_at', [$from, $to])
            ->sum('lead_financials.payout');
    }

    protected function sumRevenueForSoldInRange(int $supplierId, Carbon $from, Carbon $to): float
    {
        return (float) DB::table('lead_financials')
            ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
            ->where('leads.supplier_id', $supplierId)
            ->where('leads.status', 'sold')
            ->whereBetween('leads.received_at', [$from, $to])
            ->sum('lead_financials.revenue');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function dateRange(int $days): array
    {
        $to = today()->endOfDay();
        $from = today()->subDays($days - 1)->startOfDay();

        return [$from, $to];
    }

    protected function normalizeDays(int $days): int
    {
        return in_array($days, [7, 30, 90], true) ? $days : 30;
    }
}
