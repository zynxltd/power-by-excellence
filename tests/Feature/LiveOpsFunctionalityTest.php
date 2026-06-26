<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Models\LeadFinancial;
use App\Models\User;
use App\Support\AdminModules;
use App\Support\LeadQueueMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LiveOpsFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $ukAdmin;

    protected Account $ukAccount;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->ukAccount = Account::where('slug', 'excellence-uk')->first();
        $this->campaign = Campaign::where('reference', 'loans-uk')->first();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_operations_routes_map_to_operations_module(): void
    {
        $this->assertSame('operations', AdminModules::moduleForRoute('operations.index'));
        $this->assertSame('operations', AdminModules::moduleForRoute('live-stats'));
        $this->assertSame('operations', AdminModules::moduleForRoute('leads.index'));
        $this->assertSame('logs', AdminModules::moduleForRoute('logs.delivery'));
    }

    public function test_operations_stats_reflect_todays_leads(): void
    {
        Lead::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'sold-ops@test.test'],
            'received_at' => now(),
            'distributed_at' => now(),
        ]);

        Lead::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'status' => LeadStatus::Unsold,
            'field_data' => ['email' => 'unsold-ops@test.test'],
            'received_at' => now(),
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Operations/Index')
                ->where('stats.leads_today', fn ($v) => $v >= 2)
                ->where('stats.sold_today', fn ($v) => $v >= 1)
                ->where('stats.unsold_today', fn ($v) => $v >= 1)
            );
    }

    public function test_queue_breakdown_uses_readable_keys(): void
    {
        $counts = [
            LeadStatus::Pending->value => 3,
            LeadStatus::Validating->value => 2,
            LeadStatus::Distributing->value => 1,
            LeadStatus::Accepted->value => 4,
            LeadStatus::Quarantined->value => 2,
        ];

        $breakdown = LeadQueueMetrics::queueBreakdown($counts);

        $this->assertSame(3, $breakdown['pending']);
        $this->assertSame(3, $breakdown['processing']);
        $this->assertSame(4, $breakdown['accepted']);
        $this->assertSame(2, $breakdown['quarantined']);

        Lead::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'status' => LeadStatus::Validating,
            'field_data' => ['email' => 'processing@test.test'],
            'received_at' => now(),
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->getJson(route('live-stats'))
            ->assertOk()
            ->assertJsonStructure([
                'queue_breakdown' => ['pending', 'processing', 'accepted', 'quarantined'],
                'pipeline_summary' => ['total', 'pending', 'processing', 'sold', 'unsold', 'rejected', 'quarantined', 'duplicate'],
                'processing_leads',
                'reject_rate',
                'updated_at',
            ]);
    }

    public function test_live_stats_scopes_delivery_metrics_to_tenant(): void
    {
        $ukDelivery = Delivery::where('campaign_id', $this->campaign->id)->first();
        $ukLead = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $this->campaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => ['email' => 'uk-ping@test.test'],
            'received_at' => now(),
        ]);

        DeliveryLog::create([
            'lead_id' => $ukLead->id,
            'delivery_id' => $ukDelivery->id,
            'buyer_id' => $ukDelivery->buyer_id,
            'status' => 'failed',
            'ping_request' => ['email' => 'uk-ping@test.test'],
            'duration_ms' => 90,
        ]);

        $otherCampaign = Campaign::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'insurance-ca'))
            ->first();
        $otherDelivery = Delivery::where('campaign_id', $otherCampaign->id)->first();
        $otherLead = Lead::withoutGlobalScopes()->create([
            'account_id' => $otherCampaign->account_id,
            'campaign_id' => $otherCampaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => ['email' => 'ca-ping@test.test'],
            'received_at' => now(),
        ]);

        DeliveryLog::create([
            'lead_id' => $otherLead->id,
            'delivery_id' => $otherDelivery->id,
            'buyer_id' => $otherDelivery->buyer_id,
            'status' => 'failed',
            'ping_request' => ['email' => 'ca-ping@test.test'],
            'duration_ms' => 90,
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->getJson(route('live-stats'))
            ->assertOk()
            ->assertJsonPath('ping_posts_today', fn ($count) => $count >= 1)
            ->assertJsonPath('failed_today', fn ($count) => $count >= 1);

        $caAdmin = User::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'insurance-ca'))
            ->where('role', UserRole::AccountAdmin)
            ->first();

        $this->withServerVariables(['HTTP_HOST' => 'insurance-ca.powerbyexcellence.test'])
            ->actingAs($caAdmin)
            ->getJson(route('live-stats'))
            ->assertOk()
            ->assertJsonPath('ping_posts_today', fn ($count) => $count >= 1);
    }

    public function test_live_stats_revenue_is_tenant_scoped(): void
    {
        $ukLead = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $this->campaign->id,
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'uk-revenue@test.test'],
            'received_at' => now(),
            'distributed_at' => now(),
        ]);

        LeadFinancial::create([
            'lead_id' => $ukLead->id,
            'revenue' => 42.50,
            'payout' => 10,
            'margin' => 32.50,
            'currency' => 'GBP',
        ]);

        $otherCampaign = Campaign::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'insurance-ca'))
            ->first();

        $caLead = Lead::withoutGlobalScopes()->create([
            'account_id' => $otherCampaign->account_id,
            'campaign_id' => $otherCampaign->id,
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'ca-revenue@test.test'],
            'received_at' => now(),
            'distributed_at' => now(),
        ]);

        LeadFinancial::create([
            'lead_id' => $caLead->id,
            'revenue' => 999,
            'payout' => 100,
            'margin' => 899,
            'currency' => 'CAD',
        ]);

        $ukStats = $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->getJson(route('live-stats'))
            ->assertOk()
            ->json();

        $this->assertGreaterThanOrEqual(42.50, (float) $ukStats['revenue_today']);
        $this->assertLessThan(999, (float) $ukStats['revenue_today']);
    }

    public function test_delivery_preview_includes_method_and_links(): void
    {
        $delivery = Delivery::where('campaign_id', $this->campaign->id)->first();
        $lead = Lead::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'preview@test.test'],
            'received_at' => now(),
        ]);

        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $delivery->buyer_id,
            'status' => 'success',
            'ping_request' => ['email' => 'preview@test.test'],
            'duration_ms' => 150,
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('deliveryPreview.data', fn ($rows) => collect($rows)->contains(
                    fn ($row) => ($row['method'] ?? null) === 'ping-post' && ($row['status'] ?? null) === 'success'
                ))
                ->has('recentLeads.data')
                ->where('hourlyLeads', fn ($hours) => count($hours) === 24)
            );
    }

    public function test_campaign_filter_scopes_operations_stats(): void
    {
        $other = Campaign::where('account_id', $this->ukAccount->id)
            ->where('id', '!=', $this->campaign->id)
            ->first();

        Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $this->campaign->id,
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'filter-campaign@test.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
            'metadata' => ['quarantine_reason' => 'hold'],
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('operations.index', ['campaign_id' => $other->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.quarantined', 0)
            );
    }

    public function test_staff_without_operations_module_cannot_access_live_ops(): void
    {
        $staff = User::factory()->create([
            'account_id' => $this->ukAccount->id,
            'role' => UserRole::Staff,
            'allowed_modules' => ['reports'],
        ]);

        $this->ukHost()
            ->actingAs($staff)
            ->get(route('operations.index'))
            ->assertForbidden();

        $this->ukHost()
            ->actingAs($staff)
            ->getJson(route('live-stats'))
            ->assertForbidden();
    }

    public function test_processing_leads_appear_in_live_stats(): void
    {
        $lead = Lead::create([
            'account_id' => $this->campaign->account_id,
            'campaign_id' => $this->campaign->id,
            'status' => LeadStatus::Distributing,
            'field_data' => ['email' => 'distributing@test.test'],
            'received_at' => now(),
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->getJson(route('live-stats'))
            ->assertOk()
            ->assertJsonPath('processing_count', fn ($count) => $count >= 1)
            ->assertJsonPath('processing_leads', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['id'] === $lead->id
            ));
    }
}
