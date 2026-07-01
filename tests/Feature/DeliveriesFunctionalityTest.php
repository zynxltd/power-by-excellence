<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Models\User;
use App\Services\Delivery\DeliveryAnalyticsService;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeliveriesFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $ukAdmin;

    protected Account $ukAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->ukAccount = Account::where('slug', 'excellence-uk')->first();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_deliveries_routes_map_to_routing_module(): void
    {
        $this->assertSame('routing', AdminModules::moduleForRoute('deliveries.index'));
        $this->assertSame('routing', AdminModules::moduleForRoute('deliveries.store'));
    }

    public function test_create_delivery_persists_configuration(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('deliveries.store'), [
                'campaign_id' => $campaign->id,
                'buyer_id' => $buyer->id,
                'name' => 'QA Ping Delivery',
                'method' => 'ping_post',
                'trigger_type' => 'on_lead_arrival',
                'status' => 'active',
                'priority' => 30,
                'weight' => 100,
                'tier' => 2,
                'revenue_type' => 'dynamic',
                'revenue_amount' => 0,
                'advanced_distribution_only' => true,
                'config' => [
                    'ping_url' => '/api/v1/ping',
                    'post_url' => '/api/v1/post',
                    'revenue_field' => 'Cost',
                ],
                'caps' => ['daily' => 50],
            ])
            ->assertRedirect(route('deliveries.index'));

        $delivery = Delivery::where('name', 'QA Ping Delivery')->first();
        $this->assertNotNull($delivery);
        $this->assertSame('ping_post', $delivery->method->value);
        $this->assertTrue($delivery->advanced_distribution_only);
        $this->assertSame(50, $delivery->caps['daily']);
    }

    public function test_email_ping_post_method_can_be_saved(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('deliveries.store'), [
                'campaign_id' => $campaign->id,
                'buyer_id' => $buyer->id,
                'name' => 'Email Ping QA',
                'method' => 'email_ping_post',
                'status' => 'active',
                'revenue_type' => 'fixed',
                'revenue_amount' => 12,
                'config' => ['to' => 'buyer@example.com'],
            ])
            ->assertRedirect(route('deliveries.index'));

        $this->assertDatabaseHas('deliveries', [
            'name' => 'Email Ping QA',
            'method' => 'email_ping_post',
        ]);
    }

    public function test_store_rejects_cross_tenant_campaign(): void
    {
        $usCampaign = Campaign::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'partner-solar-us'))
            ->first();

        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('deliveries.store'), [
                'campaign_id' => $usCampaign->id,
                'buyer_id' => $buyer->id,
                'name' => 'Cross Tenant Delivery',
                'method' => 'store_lead',
                'status' => 'active',
                'revenue_type' => 'fixed',
                'revenue_amount' => 10,
            ])
            ->assertSessionHasErrors('campaign_id');

        $this->assertDatabaseMissing('deliveries', ['name' => 'Cross Tenant Delivery']);
    }

    public function test_store_rejects_cross_tenant_buyer(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $usBuyer = Buyer::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'partner-solar-us'))
            ->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('deliveries.store'), [
                'campaign_id' => $campaign->id,
                'buyer_id' => $usBuyer->id,
                'name' => 'Wrong Buyer Delivery',
                'method' => 'store_lead',
                'status' => 'active',
                'revenue_type' => 'fixed',
                'revenue_amount' => 10,
            ])
            ->assertSessionHasErrors('buyer_id');

        $this->assertDatabaseMissing('deliveries', ['name' => 'Wrong Buyer Delivery']);
    }

    public function test_delivery_index_is_tenant_scoped(): void
    {
        $ukCount = Delivery::whereHas('campaign', fn ($q) => $q->where('account_id', $this->ukAccount->id))->count();

        $response = $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('deliveries.index'))
            ->assertOk();

        $stats = $response->viewData('page')['props']['stats'];
        $this->assertSame($ukCount, $stats['total']);

        $campaignNames = collect($response->viewData('page')['props']['deliveries']['data'] ?? [])
            ->pluck('campaign.name')
            ->unique()
            ->implode(' ');

        $this->assertStringNotContainsString('Solar US', $campaignNames);
    }

    public function test_delivery_index_filters_by_method(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('deliveries.index', ['method' => 'store_lead']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('deliveries.data', fn ($rows) => count($rows) >= 1
                    && collect($rows)->every(fn ($row) => ($row['method']['value'] ?? $row['method']) === 'store_lead'))
                ->has('deliveries.links')
            );
    }

    public function test_delivery_show_has_coherent_performance_context(): void
    {
        $delivery = Delivery::whereHas('campaign', fn ($q) => $q->where('account_id', $this->ukAccount->id))
            ->where('status', 'active')
            ->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('deliveries.show', $delivery))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Deliveries/Show')
                ->where('delivery.id', $delivery->id)
                ->has('health')
                ->has('stats.success_rate')
                ->has('performance.today.attempts')
                ->has('performance.last_7_days.revenue')
                ->has('methodGuide.title')
            );
    }

    public function test_inactive_delivery_health_is_inactive_not_healthy(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => Buyer::where('account_id', $this->ukAccount->id)->value('id'),
            'name' => 'Inactive QA',
            'method' => 'store_lead',
            'status' => 'inactive',
            'revenue_type' => 'fixed',
            'revenue_amount' => 10,
        ]);

        $this->assertSame('inactive', app(DeliveryAnalyticsService::class)->healthFor($delivery));
    }

    public function test_clone_creates_independent_saved_copy(): void
    {
        $delivery = Delivery::whereHas('campaign', fn ($q) => $q->where('account_id', $this->ukAccount->id))->first();
        $originalConfig = $delivery->config;

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('deliveries.clone', $delivery))
            ->assertRedirect();

        $copy = Delivery::where('name', $delivery->name.' (copy)')->first();
        $this->assertNotNull($copy);
        $this->assertSame('saved', $copy->status);
        $this->assertNotSame($delivery->id, $copy->id);
        $this->assertEquals($originalConfig, $copy->config);
    }

    public function test_test_delivery_executes_when_campaign_has_leads(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)
            ->whereHas('leads')
            ->first();

        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => Buyer::where('account_id', $this->ukAccount->id)->value('id'),
            'name' => 'Store Test Delivery',
            'method' => 'store_lead',
            'status' => 'active',
            'revenue_type' => 'fixed',
            'revenue_amount' => 15,
        ]);

        $before = DeliveryLog::where('delivery_id', $delivery->id)->count();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('deliveries.test', $delivery))
            ->assertRedirect(route('deliveries.show', ['delivery' => $delivery, 'tab' => 'test']))
            ->assertSessionHas('success')
            ->assertSessionHas('test_lead_uuid')
            ->assertSessionHas('test_result');

        $this->assertGreaterThan($before, DeliveryLog::where('delivery_id', $delivery->id)->count());
    }

    public function test_test_delivery_uses_synthetic_lead_when_campaign_has_no_leads(): void
    {
        $campaign = Campaign::create([
            'account_id' => $this->ukAccount->id,
            'reference' => 'empty-campaign-qa',
            'name' => 'Empty Campaign QA',
            'status' => 'active',
            'currency' => 'GBP',
        ]);

        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => Buyer::where('account_id', $this->ukAccount->id)->value('id'),
            'name' => 'No Lead Test',
            'method' => 'store_lead',
            'status' => 'active',
            'revenue_type' => 'fixed',
            'revenue_amount' => 10,
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('deliveries.test', $delivery))
            ->assertRedirect(route('deliveries.show', ['delivery' => $delivery, 'tab' => 'test']))
            ->assertSessionHas('success')
            ->assertSessionHas('test_result');
    }

    public function test_rule_based_revenue_strips_incomplete_rules(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('deliveries.store'), [
                'campaign_id' => $campaign->id,
                'buyer_id' => $buyer->id,
                'name' => 'Rule Based QA',
                'method' => 'store_lead',
                'status' => 'active',
                'revenue_type' => 'rule_based',
                'revenue_rules' => [
                    ['field' => 'state', 'value' => 'CA', 'amount' => 25],
                    ['field' => '', 'value' => 'TX', 'amount' => 20],
                    ['field' => 'zipcode', 'value' => '', 'amount' => 15],
                ],
            ])
            ->assertRedirect();

        $delivery = Delivery::where('name', 'Rule Based QA')->first();
        $this->assertCount(2, $delivery->revenue_rules);
        $this->assertSame('state', $delivery->revenue_rules[0]['field']);
        $this->assertEquals(25.0, (float) $delivery->revenue_rules[0]['amount']);
        $this->assertSame('zipcode', $delivery->revenue_rules[1]['field']);
        $this->assertEquals(15.0, (float) $delivery->revenue_rules[1]['amount']);
    }

    public function test_delivery_index_paginates_results(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();

        for ($i = 0; $i < 30; $i++) {
            Delivery::create([
                'campaign_id' => $campaign->id,
                'buyer_id' => $buyer->id,
                'name' => "Paginated Delivery {$i}",
                'method' => 'store_lead',
                'status' => 'active',
                'revenue_type' => 'fixed',
                'revenue_amount' => 10,
            ]);
        }

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('deliveries.index', ['view' => 'cards']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('deliveries.data', 24)
                ->where('deliveries.total', fn ($total) => $total >= 30)
                ->has('deliveries.links')
                ->has('grouped')
            );
    }

    public function test_analytics_success_rate_is_null_without_attempts(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'name' => 'No Logs Yet',
            'method' => 'store_lead',
            'status' => 'active',
            'revenue_type' => 'fixed',
            'revenue_amount' => 10,
        ]);

        $stats = app(DeliveryAnalyticsService::class)->statsFor($delivery);

        $this->assertSame(0, $stats['last_24h_total']);
        $this->assertNull($stats['success_rate']);
    }
}
