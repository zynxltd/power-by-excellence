<?php

namespace Tests\Unit;

use App\Services\Reports\ReportMetrics;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_economics_calculates_epl_epc_and_margin_pct(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $metrics = new ReportMetrics(
            accountId: null,
            since: today()->subDays(28),
            days: 28,
        );

        $charts = $metrics->dailyCharts();
        $summary = $metrics->summary($charts);
        $kpis = $summary['kpis'];

        $this->assertArrayHasKey('epl', $kpis);
        $this->assertArrayHasKey('epc', $kpis);
        $this->assertArrayHasKey('cpa', $kpis);
        $this->assertArrayHasKey('margin_pct', $kpis);

        if ($summary['sold_period'] > 0) {
            $this->assertEquals(
                round($summary['revenue_period'] / $summary['sold_period'], 2),
                $kpis['epl']
            );
        }

        if ($summary['leads_period'] > 0) {
            $this->assertEquals(
                round($summary['revenue_period'] / $summary['leads_period'], 2),
                $kpis['epc']
            );
        }
    }

    public function test_monthly_period_uses_calendar_days(): void
    {
        $month = Carbon::now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        $metrics = new ReportMetrics(
            accountId: null,
            since: $start->copy(),
            days: $start->daysInMonth,
            monthStart: $start,
        );

        $this->assertSame($start->daysInMonth, $metrics->days());
        $this->assertSame($month, $metrics->month());
    }
}
