<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Models\Lead;
use App\Models\User;
use App\Services\Distribution\DistributionEngine;
use App\Services\Distribution\RoutingSimulatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoutingFunctionalityTest extends TestCase
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

    public function test_distribution_and_simulator_pages_load(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($this->admin)
            ->get(route('distribution.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Distribution/Index')->has('routingModes'));

        $this->actingAs($this->admin)
            ->get(route('distribution.create', ['campaign_id' => $campaign->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Distribution/Form'));

        $config = DistributionConfig::where('campaign_id', $campaign->id)->first();
        $this->assertNotNull($config);

        $this->actingAs($this->admin)
            ->get(route('distribution.show', $config))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Distribution/Show')->has('tiers'));

        $this->actingAs($this->admin)
            ->get(route('routing.simulator', ['campaign_id' => $campaign->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Routing/Simulator')->has('campaigns'));

        $this->actingAs($this->admin)
            ->get(route('features.routing'))
            ->assertOk();
    }

    public function test_routing_simulator_runs_standard_waterfall_preview(): void
    {
        $campaign = Campaign::where('reference', 'auto-insurance-uk')->first();
        $campaign->update(['use_advanced_distribution' => false]);

        $this->actingAs($this->admin)
            ->post(route('routing.simulator.run'), [
                'campaign_id' => $campaign->id,
                'field_data' => [
                    'firstname' => 'Sim',
                    'lastname' => 'Test',
                    'email' => 'sim@test.com',
                    'phone1' => '07700900123',
                    'zipcode' => 'SW1A 1AA',
                ],
            ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('simulation.mode', 'standard')
                ->where('simulation.would_sell', fn ($v) => is_bool($v))
                ->has('simulation.steps')
            );
    }

    public function test_routing_simulator_runs_advanced_ping_tree_preview(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $campaign->update(['use_advanced_distribution' => true]);

        $this->actingAs($this->admin)
            ->post(route('routing.simulator.run'), [
                'campaign_id' => $campaign->id,
                'field_data' => [
                    'firstname' => 'Sim',
                    'lastname' => 'Adv',
                    'email' => 'sim.adv@test.com',
                    'phone1' => '07700900456',
                    'zipcode' => 'EC1A 1BB',
                    'loan_amount' => 5000,
                ],
            ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('simulation.mode', 'advanced')
                ->has('simulation.steps')
            );
    }

    public function test_tenant_admin_cannot_access_other_tenant_distribution_config(): void
    {
        $caConfig = DistributionConfig::withoutGlobalScopes()
            ->whereHas('campaign', fn ($q) => $q->withoutGlobalScopes()->whereHas('account', fn ($a) => $a->where('slug', 'insurance-ca')))
            ->first();

        $this->assertNotNull($caConfig);

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($this->admin)
            ->get('/distribution/'.$caConfig->id)
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->get('/distribution/'.$caConfig->id)
            ->assertNotFound();
    }

    public function test_standard_waterfall_sells_to_first_eligible_delivery(): void
    {
        Http::fake(['https://winner.test/*' => Http::response(['Success' => true], 200)]);

        $account = Account::create([
            'name' => 'Routing WF',
            'slug' => 'routing-wf',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'WF Campaign',
            'reference' => 'wf-campaign',
            'status' => 'active',
            'country' => 'GB',
            'currency' => 'GBP',
            'sell_mode' => 'exclusive',
            'payout_amount' => 5,
            'floor_price' => 0,
            'use_advanced_distribution' => false,
        ]);

        $buyer = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'wf-buyer',
            'name' => 'WF Buyer',
            'status' => 'active',
            'credit_balance' => 500,
        ]);

        Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Winner',
            'method' => 'direct_post',
            'status' => 'active',
            'trigger_type' => 'on_lead_arrival',
            'priority' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => 25,
            'config' => ['url' => 'https://winner.test/post'],
        ]);

        $lead = Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => ['email' => 'wf@test.com', 'firstname' => 'W', 'lastname' => 'F'],
            'received_at' => now(),
        ]);

        $result = app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertTrue($result->sold);
        $this->assertSame(LeadStatus::Sold, $lead->fresh()->status);
    }

    public function test_advanced_waterfall_tier_sells_when_eligible(): void
    {
        Http::fake(['https://tier-buyer.test/*' => Http::response(['Success' => true], 200)]);

        $account = Account::create([
            'name' => 'Routing Tier',
            'slug' => 'routing-tier',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'Tier Campaign',
            'reference' => 'tier-campaign',
            'status' => 'active',
            'country' => 'GB',
            'currency' => 'GBP',
            'sell_mode' => 'exclusive',
            'payout_amount' => 5,
            'floor_price' => 0,
            'use_advanced_distribution' => true,
        ]);

        $buyer = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'tier-buyer',
            'name' => 'Tier Buyer',
            'status' => 'active',
            'credit_balance' => 500,
        ]);

        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Tier Delivery',
            'method' => 'direct_post',
            'status' => 'active',
            'trigger_type' => 'on_lead_arrival',
            'priority' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => 30,
            'config' => ['url' => 'https://tier-buyer.test/post'],
        ]);

        DistributionConfig::create([
            'campaign_id' => $campaign->id,
            'name' => 'Single Tier',
            'is_active' => true,
            'config' => [
                'groups' => [
                    [
                        'name' => 'Tier 1',
                        'mode' => 'waterfall',
                        'delivery_ids' => [$delivery->id],
                    ],
                ],
            ],
        ]);

        $lead = Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => ['email' => 'tier@test.com', 'firstname' => 'T', 'lastname' => '1'],
            'received_at' => now(),
        ]);

        $result = app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertTrue($result->sold);
    }

    public function test_simulator_service_marks_ineligible_buyer_without_credit(): void
    {
        $campaign = Campaign::where('reference', 'auto-insurance-uk')->first();
        $campaign->update(['use_advanced_distribution' => false]);

        $delivery = $campaign->deliveries()->first();
        $delivery->buyer->update(['credit_balance' => 0]);
        $campaign->account->update([
            'settings' => array_merge($campaign->account->settings ?? [], ['require_buyer_prepay' => true]),
        ]);

        $result = app(RoutingSimulatorService::class)->simulate($campaign->fresh(), [
            'email' => 'nocredit@test.com',
            'firstname' => 'No',
            'lastname' => 'Credit',
        ]);

        $this->assertFalse($result['would_sell']);
        $ineligible = collect($result['steps'])->first(fn ($s) => ! ($s['eligible'] ?? true));
        $this->assertNotNull($ineligible);
        $this->assertContains('Insufficient buyer credit', $ineligible['skip_reasons']);
    }

    public function test_distribution_store_requires_at_least_one_tier(): void
    {
        $campaign = Campaign::where('account_id', $this->admin->account_id)->first();

        $this->actingAs($this->admin)
            ->post(route('distribution.store'), [
                'campaign_id' => $campaign->id,
                'name' => 'Invalid',
                'is_active' => true,
                'groups' => [],
            ])
            ->assertSessionHasErrors('groups');
    }
}
