<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadFinancial;
use App\Models\User;
use App\Services\Api\ApiKeyService;
use App\Services\Reports\ReportMetrics;
use App\Support\AdminModules;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportsFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->account = Account::where('slug', 'excellence-uk')->first();
    }

    protected function tenantRequest()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_reports_route_maps_to_reports_module(): void
    {
        $this->assertSame('reports', AdminModules::moduleForRoute('reports.index'));
    }

    public function test_summary_kpis_match_known_lead_financials(): void
    {
        $account = Account::create([
            'name' => 'Report Metrics Isolated',
            'slug' => 'report-metrics-isolated',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'Report KPI Campaign',
            'reference' => 'report-kpi-campaign',
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
        ]);

        $today = today();

        $this->createLead($campaign, 'sold', $today, 100, 60, 40);
        $this->createLead($campaign, 'sold', $today, 50, 30, 20);
        $this->createLead($campaign, 'unsold', $today);
        $this->createLead($campaign, 'rejected', $today);

        $metrics = new ReportMetrics($account->id, $today->copy()->subDays(6), 7);
        $summary = $metrics->summary($metrics->dailyCharts());

        $this->assertSame(4, $summary['leads_period']);
        $this->assertSame(2, $summary['sold_period']);
        $this->assertSame(1, $summary['unsold_period']);
        $this->assertSame(1, $summary['rejected_period']);
        $this->assertSame(150.0, $summary['revenue_period']);
        $this->assertSame(90.0, $summary['payout_period']);
        $this->assertSame(60.0, $summary['margin_period']);
        $this->assertSame(50.0, $summary['conversion']);
        $this->assertSame(66.7, $summary['sell_through']);
        $this->assertSame(25.0, $summary['reject_rate']);

        $kpis = $summary['kpis'];
        $this->assertSame(75.0, $kpis['epl']);
        $this->assertSame(37.5, $kpis['epc']);
        $this->assertSame(45.0, $kpis['cpa']);
        $this->assertSame(22.5, $kpis['cpl']);
        $this->assertSame(30.0, $kpis['mpl']);
        $this->assertSame(40.0, $kpis['margin_pct']);
        $this->assertNotEquals($kpis['epl'], $kpis['cpl']);
        $this->assertNotEquals($kpis['epc'], $kpis['cpa']);
    }

    public function test_reports_page_renders_coherent_summary_for_tenant(): void
    {
        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('reports.index', ['days' => 28]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Index')
                ->where('currency', 'GBP')
                ->has('charts.labels')
                ->has('summary.kpis.epl')
                ->has('summary.kpis.cpl')
                ->has('byBuyer')
                ->has('bySupplier')
                ->has('byCampaign')
                ->has('tierSummary')
                ->has('pingTree.campaign_name')
                ->where('summary.leads_period', fn ($count) => $count >= 1)
                ->where('summary.kpis.margin_pct', fn ($pct) => $pct >= 0 && $pct <= 100)
            );
    }

    public function test_monthly_filter_scopes_charts_to_calendar_month(): void
    {
        $account = Account::create([
            'name' => 'Monthly Report Isolated',
            'slug' => 'monthly-report-isolated',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'Monthly Report Campaign',
            'reference' => 'monthly-report-campaign',
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
        ]);

        $inMonth = Carbon::create(2026, 3, 10);
        $outOfMonth = Carbon::create(2026, 2, 20);

        $this->createLead($campaign, 'sold', $inMonth, 80, 50, 30);
        $this->createLead($campaign, 'sold', $outOfMonth, 200, 100, 100);

        $request = \Illuminate\Http\Request::create('/reports', 'GET', ['month' => '2026-03']);
        $request->attributes->set('account', $account);
        $metrics = ReportMetrics::fromRequest($request);

        $summary = $metrics->summary($metrics->dailyCharts());

        $this->assertSame('2026-03', $metrics->month());
        $this->assertSame(31, $metrics->days());
        $this->assertSame(1, $summary['sold_period']);
        $this->assertSame(80.0, $summary['revenue_period']);
    }

    public function test_api_revenue_report_is_scoped_to_api_key_tenant(): void
    {
        $ukToken = app(ApiKeyService::class)->create([
            'account_id' => $this->account->id,
            'name' => 'UK reports',
            'type' => 'administrator',
            'permissions' => ['reports.read'],
        ])['token'];

        $other = Account::where('slug', 'insurance-ca')->first();
        $otherCampaign = Campaign::withoutGlobalScopes()->where('account_id', $other->id)->first();

        $otherLead = Lead::withoutGlobalScopes()->create([
            'account_id' => $other->id,
            'campaign_id' => $otherCampaign->id,
            'status' => 'sold',
            'field_data' => ['email' => 'foreign-revenue@test.test'],
            'received_at' => now(),
            'distributed_at' => now(),
        ]);

        LeadFinancial::create([
            'lead_id' => $otherLead->id,
            'revenue' => 9999,
            'payout' => 5000,
            'margin' => 4999,
            'currency' => 'CAD',
        ]);

        $response = $this->getJson(
            '/api/v1/reports/revenue?from='.now()->subDay()->toDateString().'&to='.now()->toDateString(),
            ['Authorization' => 'Bearer '.$ukToken]
        )->assertOk();

        $this->assertLessThan(9999, $response->json('revenue'));
    }

    public function test_reports_by_campaign_excludes_other_tenant(): void
    {
        $ukCampaign = Campaign::where('account_id', $this->account->id)->first();
        $usAccount = Account::where('slug', 'partner-solar-us')->first();
        $usCampaign = Campaign::withoutGlobalScopes()->where('account_id', $usAccount->id)->first();

        $metrics = new ReportMetrics($this->account->id, today()->subDays(28), 28);
        $ids = collect($metrics->byCampaign(100)->items())->pluck('campaign_id');

        $this->assertTrue($ids->contains($ukCampaign->id));
        $this->assertFalse($ids->contains($usCampaign->id));
    }

    public function test_ping_tree_panel_prefers_ten_tier_config_name(): void
    {
        $campaign = Campaign::where('reference', 'auto-insurance-uk')->first();

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('reports.index', ['days' => 28]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pingTree.campaign_id', $campaign->id)
                ->where('pingTree.config_name', fn ($name) => str_contains(strtolower($name ?? ''), '10-tier')
                    || str_contains(strtolower($name ?? ''), 'ping tree'))
                ->where('pingTree.tier_count', fn ($count) => $count >= 1)
            );
    }

    protected function createLead(
        Campaign $campaign,
        string $status,
        Carbon $receivedAt,
        ?float $revenue = null,
        ?float $payout = null,
        ?float $margin = null,
    ): Lead {
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => $status,
            'field_data' => ['email' => uniqid('report-', true).'@test.test'],
            'received_at' => $receivedAt,
            'distributed_at' => in_array($status, ['sold', 'unsold'], true) ? $receivedAt : null,
        ]);

        if ($revenue !== null) {
            LeadFinancial::create([
                'lead_id' => $lead->id,
                'revenue' => $revenue,
                'payout' => $payout,
                'margin' => $margin,
                'currency' => $campaign->currency,
            ]);
        }

        return $lead;
    }
}
