<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use App\Services\Reports\ReportMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReportsRigorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
    }

    public function test_invalid_period_defaults_to_28_days(): void
    {
        $request = Request::create('/reports', 'GET', ['days' => 99]);
        $metrics = ReportMetrics::fromRequest($request);

        $this->assertSame(28, $metrics->days());
    }

    public function test_valid_periods_are_accepted(): void
    {
        foreach ([7, 14, 28, 30, 60, 90] as $days) {
            $request = Request::create('/reports', 'GET', ['days' => $days]);
            $this->assertSame($days, ReportMetrics::fromRequest($request)->days());
        }
    }

    public function test_monthly_filter_uses_calendar_month_length(): void
    {
        $request = Request::create('/reports', 'GET', ['month' => '2026-03']);
        $metrics = ReportMetrics::fromRequest($request);

        $this->assertSame('2026-03', $metrics->month());
        $this->assertSame(31, $metrics->days());
    }

    public function test_invalid_month_format_falls_back_to_day_range(): void
    {
        $request = Request::create('/reports', 'GET', ['month' => 'not-a-month', 'days' => 14]);
        $metrics = ReportMetrics::fromRequest($request);

        $this->assertNull($metrics->month());
        $this->assertSame(14, $metrics->days());
    }

    public function test_kpis_are_zero_when_no_sold_leads_in_period(): void
    {
        $campaign = Campaign::first();

        Lead::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'sold')
            ->update([
                'received_at' => now()->subYear(),
                'distributed_at' => now()->subYear(),
            ]);

        $metrics = new ReportMetrics($campaign->account_id, today()->subDays(7), 7);
        $charts = $metrics->dailyCharts();
        $summary = $metrics->summary($charts);

        $this->assertSame(0, $summary['sold_period']);
        $this->assertSame(0.0, $summary['kpis']['epl']);
        $this->assertSame(0.0, $summary['kpis']['cpa']);
    }

    public function test_reports_page_includes_campaign_and_sid_breakdowns(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.index', ['days' => 28]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('byCampaign')
                ->has('bySid')
                ->has('summary.kpis.epl')
                ->has('summary.kpis.epc')
                ->has('summary.kpis.margin_pct')
                ->has('summary.delivery.success_rate')
                ->has('charts.payout')
                ->has('charts.margin')
                ->has('leadStatusBreakdown')
            );
    }

    public function test_reports_scoped_to_tenant_on_subdomain(): void
    {
        $ukAccount = Account::where('slug', 'excellence-uk')->first();
        $usAccount = Account::where('slug', 'partner-solar-us')->first();

        $ukCampaignIds = Campaign::withoutGlobalScopes()->where('account_id', $ukAccount->id)->pluck('id');
        $usCampaignIds = Campaign::withoutGlobalScopes()->where('account_id', $usAccount->id)->pluck('id');

        $ukMetrics = new ReportMetrics($ukAccount->id, today()->subDays(28), 28);
        $usMetrics = new ReportMetrics($usAccount->id, today()->subDays(28), 28);

        $ukReportIds = collect($ukMetrics->byCampaign(100)->items())->pluck('campaign_id');
        $usReportIds = collect($usMetrics->byCampaign(100)->items())->pluck('campaign_id');

        $this->assertTrue($ukReportIds->intersect($ukCampaignIds)->isNotEmpty());
        $this->assertTrue($usReportIds->intersect($usCampaignIds)->isNotEmpty());
        $this->assertEmpty($ukReportIds->intersect($usCampaignIds));
        $this->assertEmpty($usReportIds->intersect($ukCampaignIds));
    }
}
