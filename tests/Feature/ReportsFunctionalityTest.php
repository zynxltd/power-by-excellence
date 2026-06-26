<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Models\LeadFinancial;
use App\Models\User;
use App\Services\Api\ApiKeyService;
use App\Services\Reports\ReportMetrics;
use App\Support\AdminModules;
use Carbon\Carbon;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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
        $this->seed(PlatformSeeder::class);
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
        $this->assertSame([], $summary['kpis_by_currency']);
    }

    public function test_kpis_by_currency_when_multiple_currencies_in_period(): void
    {
        $account = Account::create([
            'name' => 'Multi Currency Metrics',
            'slug' => 'multi-currency-metrics',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
        ]);

        $gbpCampaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'GBP Campaign',
            'reference' => 'gbp-campaign',
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
        ]);

        $usdCampaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'USD Campaign',
            'reference' => 'usd-campaign',
            'country' => 'US',
            'currency' => 'USD',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
        ]);

        $today = today();

        $this->createLead($gbpCampaign, 'sold', $today, 100, 60, 40);
        $this->createLead($gbpCampaign, 'sold', $today, 50, 30, 20);
        $this->createLead($usdCampaign, 'sold', $today, 80, 40, 40);

        $metrics = new ReportMetrics($account->id, $today->copy()->subDays(6), 7);
        $summary = $metrics->summary($metrics->dailyCharts());

        $this->assertCount(2, $summary['kpis_by_currency']);

        $gbp = collect($summary['kpis_by_currency'])->firstWhere('currency', 'GBP');
        $usd = collect($summary['kpis_by_currency'])->firstWhere('currency', 'USD');

        $this->assertSame(75.0, $gbp['epl']);
        $this->assertSame(75.0, $gbp['epc']);
        $this->assertSame(40.0, $gbp['margin_pct']);
        $this->assertSame(80.0, $usd['epl']);
        $this->assertSame(80.0, $usd['epc']);
        $this->assertSame(50.0, $usd['margin_pct']);
    }

    public function test_summary_includes_lead_quality_metrics(): void
    {
        $account = Account::create([
            'name' => 'Quality Metrics Isolated',
            'slug' => 'quality-metrics-isolated',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'Quality KPI Campaign',
            'reference' => 'quality-kpi-campaign',
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
        ]);

        $today = today();

        Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'status' => 'accepted',
            'field_data' => [
                'email' => 'excellent@test.test',
                'phone1' => '07700900123',
                'zipcode' => 'SW1A 1AA',
                'lastname' => 'Excellent',
            ],
            'metadata' => [
                'quality_score' => 90,
                'email_validation' => ['passed' => true],
                'hlr_validation' => ['passed' => true],
            ],
            'received_at' => $today,
        ]);

        Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'status' => 'rejected',
            'field_data' => ['email' => 'poor@test.test'],
            'metadata' => [
                'quality_score' => 35,
                'email_validation' => ['passed' => false],
                'hlr_validation' => ['passed' => false],
            ],
            'received_at' => $today,
        ]);

        $metrics = new ReportMetrics($account->id, $today->copy()->subDays(6), 7);
        $quality = $metrics->summary($metrics->dailyCharts())['quality'];

        $this->assertSame(2, $quality['leads_scored']);
        $this->assertSame(62.5, $quality['avg_score']);
        $this->assertSame(1, $quality['excellent']);
        $this->assertSame(1, $quality['poor']);
        $this->assertSame(2, $quality['email_checked']);
        $this->assertSame(50.0, $quality['email_pass_rate']);
        $this->assertSame(2, $quality['hlr_checked']);
        $this->assertSame(50.0, $quality['hlr_pass_rate']);
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
                ->has('filters')
                ->has('filterOptions.campaigns')
                ->has('charts.labels')
                ->has('summary.kpis.epl')
                ->has('summary.kpis.cpl')
                ->has('summary.quality.avg_score')
                ->has('summary.quality.email_pass_rate')
                ->has('summary.quality.hlr_pass_rate')
                ->has('summary.revenue_by_currency')
                ->has('byBuyer')
                ->has('bySupplier')
                ->has('byCampaign')
                ->has('tierSummary')
                ->has('pingTreeCampaigns')
                ->where('summary.leads_period', fn ($count) => $count >= 1)
                ->where('summary.kpis.margin_pct', fn ($pct) => $pct >= 0 && $pct <= 100)
            );
    }

    public function test_tenant_reports_page_accepts_custom_date_range_filter(): void
    {
        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('reports.index', [
                'date_from' => '2026-03-10',
                'date_to' => '2026-03-20',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Index')
                ->where('filters.date_from', '2026-03-10')
                ->where('filters.date_to', '2026-03-20')
                ->where('periodLabel', fn (string $label) => str_contains($label, 'Mar'))
            );
    }

    public function test_custom_date_range_filter_scopes_metrics(): void
    {
        $account = Account::create([
            'name' => 'Range Report',
            'slug' => 'range-report',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'Range Campaign',
            'reference' => 'range-campaign',
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
        ]);

        $this->createLead($campaign, 'sold', Carbon::parse('2026-03-10'), 50, 30, 20);
        $this->createLead($campaign, 'sold', Carbon::parse('2026-03-20'), 70, 40, 30);
        $this->createLead($campaign, 'sold', Carbon::parse('2026-04-01'), 999, 500, 499);

        $request = Request::create('/reports', 'GET', [
            'date_from' => '2026-03-10',
            'date_to' => '2026-03-20',
        ]);
        $request->attributes->set('account', $account);
        $metrics = ReportMetrics::fromRequest($request);
        $summary = $metrics->summary($metrics->dailyCharts());

        $this->assertSame(2, $summary['sold_period']);
        $this->assertSame(120.0, $summary['revenue_period']);
        $this->assertStringContainsString('Mar', $metrics->periodLabel());
    }

    public function test_multi_currency_revenue_is_reported_per_currency_not_summed_in_ui_payload(): void
    {
        $account = Account::create([
            'name' => 'Multi Currency Report',
            'slug' => 'multi-currency-report',
            'default_currency' => 'CAD',
            'default_country' => 'CA',
            'is_active' => true,
        ]);

        $cadCampaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'Canada Home',
            'reference' => 'ca-home',
            'country' => 'CA',
            'currency' => 'CAD',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
        ]);

        $usdCampaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'US Auto',
            'reference' => 'us-auto',
            'country' => 'US',
            'currency' => 'USD',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
        ]);

        $today = today();
        $this->createLead($cadCampaign, 'sold', $today, 100, 60, 40);
        $this->createLead($usdCampaign, 'sold', $today, 50, 30, 20);

        $metrics = new ReportMetrics($account->id, $today->copy()->subDays(6), 7, null, $today);
        $summary = $metrics->summary($metrics->dailyCharts());

        $this->assertTrue(collect($summary['revenue_by_currency'])->pluck('currency')->contains('CAD'));
        $this->assertTrue(collect($summary['revenue_by_currency'])->pluck('currency')->contains('USD'));
        $this->assertSame(150.0, $summary['revenue_period']);

        $cadOnly = new ReportMetrics($account->id, $today->copy()->subDays(6), 7, null, $today, null, 'CAD');
        $cadSummary = $cadOnly->summary($cadOnly->dailyCharts());
        $this->assertSame(100.0, $cadSummary['revenue_period']);
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

        $request = Request::create('/reports', 'GET', ['month' => '2026-03']);
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

    public function test_ping_tree_campaigns_lists_all_active_ping_trees_for_tenant(): void
    {
        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('reports.index', ['days' => 28]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pingTreeCampaigns', fn ($campaigns) => count($campaigns) >= 1)
            );
    }

    public function test_lead_drilldown_filters_from_reports_context(): void
    {
        $campaign = Campaign::where('account_id', $this->account->id)->first();
        $from = today()->subDays(3)->toDateString();
        $to = today()->toDateString();

        Lead::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'status' => 'rejected',
            'field_data' => ['email' => 'failed@test.test'],
            'metadata' => [
                'quality_score' => 35,
                'email_validation' => ['passed' => false],
            ],
            'received_at' => today(),
        ]);

        Lead::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'status' => 'sold',
            'field_data' => ['email' => 'good@test.test'],
            'metadata' => [
                'quality_score' => 90,
                'email_validation' => ['passed' => true],
            ],
            'received_at' => today(),
            'distributed_at' => today(),
            'redirect_followed_at' => now(),
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('leads.index', [
                'from_date' => $from,
                'to_date' => $to,
                'validation' => 'email_failed',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.validation', 'email_failed')
                ->where('leads.total', fn ($total) => $total >= 1)
            );

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('leads.index', [
                'from_date' => $from,
                'to_date' => $to,
                'quality_min' => 80,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('leads.total', fn ($total) => $total >= 1)
            );

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('leads.index', [
                'status' => 'sold',
                'redirect' => 'followed',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.redirect', 'followed')
                ->where('leads.total', 1)
            );
    }

    public function test_delivery_health_is_null_when_no_leads_in_period(): void
    {
        $account = Account::create([
            'name' => 'Delivery Health Empty',
            'slug' => 'delivery-health-empty',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'Delivery Health Campaign',
            'reference' => 'delivery-health-campaign',
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
        ]);

        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => Buyer::create([
                'account_id' => $account->id,
                'name' => 'Health Buyer',
                'reference' => 'health-buyer',
            ])->id,
            'name' => 'Health Delivery',
            'method' => 'ping_post',
            'tier' => 1,
            'is_active' => true,
        ]);

        $lead = $this->createLead($campaign, 'sold', today()->subDays(10), 25, 15, 10);

        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $delivery->buyer_id,
            'status' => 'success',
            'revenue' => 25,
            'duration_ms' => 45,
            'created_at' => today(),
            'updated_at' => today(),
        ]);

        $metrics = new ReportMetrics($account->id, today(), 1, null, today());
        $summary = $metrics->summary($metrics->dailyCharts());

        $this->assertSame(0, $summary['leads_period']);
        $this->assertSame(0, $summary['delivery']['attempts']);
        $this->assertNull($summary['delivery']['success_rate']);
        $this->assertNull($summary['delivery']['avg_duration_ms']);
        $this->assertEmpty($metrics->tierSummary()->items());
        $this->assertEmpty($metrics->deliveryPerformance()->items());
    }

    public function test_delivery_health_counts_pings_for_leads_received_in_period(): void
    {
        $account = Account::create([
            'name' => 'Delivery Health Sample',
            'slug' => 'delivery-health-sample',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'Delivery Sample Campaign',
            'reference' => 'delivery-sample-campaign',
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
        ]);

        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => Buyer::create([
                'account_id' => $account->id,
                'name' => 'Sample Buyer',
                'reference' => 'sample-buyer',
            ])->id,
            'name' => 'Sample Delivery',
            'method' => 'ping_post',
            'tier' => 1,
            'is_active' => true,
        ]);

        $lead = $this->createLead($campaign, 'sold', today(), 25, 15, 10);

        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $delivery->buyer_id,
            'status' => 'success',
            'revenue' => 25,
            'duration_ms' => 50,
            'created_at' => today(),
            'updated_at' => today(),
        ]);

        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $delivery->buyer_id,
            'status' => 'failed',
            'revenue' => 0,
            'duration_ms' => 40,
            'created_at' => today(),
            'updated_at' => today(),
        ]);

        $metrics = new ReportMetrics($account->id, today(), 1, null, today());
        $summary = $metrics->summary($metrics->dailyCharts());

        $this->assertSame(1, $summary['leads_period']);
        $this->assertSame(2, $summary['delivery']['attempts']);
        $this->assertSame(50.0, $summary['delivery']['success_rate']);
        $this->assertSame(45, $summary['delivery']['avg_duration_ms']);
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
