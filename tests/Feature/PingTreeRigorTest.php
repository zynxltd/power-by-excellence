<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\DistributionConfig;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\User;
use App\Services\Distribution\DistributionEngine;
use App\Services\Distribution\RoutingSimulatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PingTreeRigorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_waterfall_cascades_to_second_tier_when_first_tier_fails(): void
    {
        Http::fake([
            'https://tier1-fail.test/*' => Http::response(['Success' => false], 422),
        ]);

        [$campaign, $tier1, $tier2] = $this->twoTierTree(
            tier1Mode: 'waterfall',
            tier1Deliveries: fn (Campaign $c, Buyer $b) => [
                $this->directDelivery($c, $b, 'Tier 1 Fail', 'https://tier1-fail.test/post', 25),
            ],
            tier2Deliveries: fn (Campaign $c, Buyer $b) => [
                $this->storeDelivery($c, $b, 'Tier 2 Store', 18),
            ],
        );

        $lead = $this->acceptedLead($campaign);
        $result = app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertTrue($result->sold);
        $this->assertSame(LeadStatus::Sold, $lead->fresh()->status);
        $this->assertDatabaseHas('delivery_logs', [
            'lead_id' => $lead->id,
            'delivery_id' => $tier1->id,
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('delivery_logs', [
            'lead_id' => $lead->id,
            'delivery_id' => $tier2->id,
            'status' => 'success',
        ]);
    }

    public function test_exclusive_advanced_stops_after_first_tier_sale(): void
    {
        Http::fake([
            'https://tier1-win.test/*' => Http::response(['Success' => true], 200),
            'https://tier2-never.test/*' => Http::response(['Success' => true], 200),
        ]);

        [$campaign, $tier1, $tier2] = $this->twoTierTree(
            tier1Mode: 'waterfall',
            tier1Deliveries: fn (Campaign $c, Buyer $b) => [
                $this->directDelivery($c, $b, 'Tier 1 Win', 'https://tier1-win.test/post', 30),
            ],
            tier2Deliveries: fn (Campaign $c, Buyer $b) => [
                $this->directDelivery($c, $b, 'Tier 2 Never', 'https://tier2-never.test/post', 20),
            ],
            sellMode: 'exclusive',
        );

        $lead = $this->acceptedLead($campaign);
        app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertSame(LeadStatus::Sold, $lead->fresh()->status);
        $this->assertDatabaseMissing('delivery_logs', [
            'lead_id' => $lead->id,
            'delivery_id' => $tier2->id,
        ]);
    }

    public function test_parallel_auction_awards_highest_bid_and_marks_losers_outbid(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, 'high-bid')) {
                return Http::response(['Success' => true, 'Cost' => 42, 'PingID' => 'high'], 200);
            }

            if (str_contains($url, 'low-bid')) {
                return Http::response(['Success' => true, 'Cost' => 18, 'PingID' => 'low'], 200);
            }

            if (str_contains($url, '/post')) {
                return Http::response(['Success' => true, 'Approved' => true], 200);
            }

            return Http::response(['Success' => false], 422);
        });

        [$campaign, $high, $low] = $this->auctionTierCampaign(floor: 15);

        $lead = $this->acceptedLead($campaign);
        $result = app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertTrue($result->sold);
        $this->assertSame(1, DeliveryLog::where('lead_id', $lead->id)->where('status', 'success')->count());

        $winnerLog = DeliveryLog::where('lead_id', $lead->id)->where('status', 'success')->first();
        $this->assertSame($high->id, $winnerLog->delivery_id);

        $this->assertDatabaseHas('delivery_logs', [
            'lead_id' => $lead->id,
            'delivery_id' => $low->id,
            'status' => 'outbid',
        ]);
    }

    public function test_parallel_auction_below_floor_cascades_to_store_fallback_tier(): void
    {
        Http::fake([
            'https://low-bid.test/api/v1/ping' => Http::response(['Success' => true, 'Cost' => 8, 'PingID' => 'low'], 200),
        ]);

        [$campaign, , $store] = $this->twoTierTree(
            tier1Mode: 'parallel_auction',
            tier1Floor: 25,
            tier1Deliveries: fn (Campaign $c, Buyer $b) => [
                $this->pingDelivery($c, $b, 'Low Bidder', 'https://low-bid.test/api/v1/ping', 'https://low-bid.test/api/v1/post'),
            ],
            tier2Deliveries: fn (Campaign $c, Buyer $b) => [
                $this->storeDelivery($c, $b, 'Store Fallback', 20),
            ],
        );

        $lead = $this->acceptedLead($campaign);
        $result = app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertTrue($result->sold);
        $this->assertDatabaseHas('delivery_logs', [
            'lead_id' => $lead->id,
            'delivery_id' => $store->id,
            'status' => 'success',
        ]);
    }

    public function test_empty_tier_is_skipped_and_next_tier_runs(): void
    {
        [$campaign, , $store] = $this->twoTierTree(
            tier1Mode: 'waterfall',
            tier1Deliveries: fn () => [],
            tier2Deliveries: fn (Campaign $c, Buyer $b) => [
                $this->storeDelivery($c, $b, 'Catch All Store', 16),
            ],
        );

        $lead = $this->acceptedLead($campaign);
        $result = app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertTrue($result->sold);
        $this->assertDatabaseHas('delivery_logs', [
            'lead_id' => $lead->id,
            'delivery_id' => $store->id,
            'status' => 'success',
        ]);
    }

    public function test_inactive_delivery_excluded_from_tier_waterfall(): void
    {
        Http::fake([
            'https://active-buyer.test/*' => Http::response(['Success' => true], 200),
        ]);

        $account = $this->makeAccount();
        $buyer = $this->makeBuyer($account);
        $campaign = $this->makeCampaign($account);

        $inactive = $this->directDelivery($campaign, $buyer, 'Inactive', 'https://inactive.test/post', 30);
        $inactive->update(['status' => 'inactive']);

        $active = $this->directDelivery($campaign, $buyer, 'Active', 'https://active-buyer.test/post', 22);

        $this->makeConfig($campaign, [
            [
                'name' => 'Tier 1',
                'mode' => 'waterfall',
                'delivery_ids' => [$inactive->id, $active->id],
            ],
        ]);

        $lead = $this->acceptedLead($campaign);
        app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertDatabaseMissing('delivery_logs', [
            'lead_id' => $lead->id,
            'delivery_id' => $inactive->id,
        ]);
        $this->assertDatabaseHas('delivery_logs', [
            'lead_id' => $lead->id,
            'delivery_id' => $active->id,
            'status' => 'success',
        ]);
    }

    public function test_advanced_distribution_disabled_uses_standard_priority_routing(): void
    {
        Http::fake(['https://standard.test/*' => Http::response(['Success' => true], 200)]);

        $account = $this->makeAccount();
        $buyer = $this->makeBuyer($account);
        $campaign = $this->makeCampaign($account, ['use_advanced_distribution' => false]);

        $standard = $this->directDelivery($campaign, $buyer, 'Standard Route', 'https://standard.test/post', 24, [
            'trigger_type' => 'on_lead_arrival',
            'advanced_distribution_only' => false,
        ]);

        $advancedOnly = $this->directDelivery($campaign, $buyer, 'Advanced Only', 'https://advanced.test/post', 99, [
            'advanced_distribution_only' => true,
        ]);

        $this->makeConfig($campaign, [
            ['name' => 'Ignored tier', 'mode' => 'waterfall', 'delivery_ids' => [$advancedOnly->id]],
        ]);

        $lead = $this->acceptedLead($campaign);
        app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertDatabaseHas('delivery_logs', [
            'lead_id' => $lead->id,
            'delivery_id' => $standard->id,
            'status' => 'success',
        ]);
        $this->assertDatabaseMissing('delivery_logs', [
            'lead_id' => $lead->id,
            'delivery_id' => $advancedOnly->id,
        ]);
    }

    public function test_tier_entry_filter_allows_matching_lead_to_sell(): void
    {
        Http::fake(['https://ca-buyer.test/*' => Http::response(['Success' => true], 200)]);

        $account = $this->makeAccount();
        $buyer = $this->makeBuyer($account);
        $campaign = $this->makeCampaign($account);
        $delivery = $this->directDelivery($campaign, $buyer, 'CA Buyer', 'https://ca-buyer.test/post', 27);

        $this->makeConfig($campaign, [
            [
                'name' => 'CA only',
                'mode' => 'waterfall',
                'delivery_ids' => [$delivery->id],
                'rules' => [
                    'operator' => 'and',
                    'conditions' => [
                        ['field' => 'state', 'op' => 'eq', 'value' => 'CA'],
                    ],
                ],
            ],
        ]);

        $lead = $this->acceptedLead($campaign, ['state' => 'CA', 'email' => 'ca@test.com']);
        $result = app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertTrue($result->sold);
        $this->assertDatabaseMissing('lead_events', [
            'lead_id' => $lead->id,
            'event_type' => 'distribution.tier_filtered',
        ]);
    }

    public function test_round_robin_rotates_between_eligible_deliveries(): void
    {
        Http::fake([
            'https://rr-a.test/*' => Http::response(['Success' => true], 200),
            'https://rr-b.test/*' => Http::response(['Success' => true], 200),
        ]);

        $account = $this->makeAccount();
        $buyer = $this->makeBuyer($account);
        $campaign = $this->makeCampaign($account, ['sell_mode' => 'shared', 'max_sells' => 10]);

        $a = $this->directDelivery($campaign, $buyer, 'RR A', 'https://rr-a.test/post', 20);
        $b = $this->directDelivery($campaign, $buyer, 'RR B', 'https://rr-b.test/post', 20);

        $this->makeConfig($campaign, [
            [
                'name' => 'Round robin tier',
                'mode' => 'round_robin',
                'delivery_ids' => [$a->id, $b->id],
            ],
        ]);

        $engine = app(DistributionEngine::class);

        $lead1 = $this->acceptedLead($campaign, ['email' => 'rr1@test.com']);
        $engine->distribute($lead1->fresh());
        $firstWinner = DeliveryLog::where('lead_id', $lead1->id)->where('status', 'success')->value('delivery_id');

        $lead2 = $this->acceptedLead($campaign, ['email' => 'rr2@test.com']);
        $engine->distribute($lead2->fresh());
        $secondWinner = DeliveryLog::where('lead_id', $lead2->id)->where('status', 'success')->value('delivery_id');

        $this->assertNotNull($firstWinner);
        $this->assertNotNull($secondWinner);
        $this->assertNotSame($firstWinner, $secondWinner);
    }

    public function test_weighted_mode_sells_through_single_eligible_delivery(): void
    {
        $account = $this->makeAccount();
        $buyer = $this->makeBuyer($account);
        $campaign = $this->makeCampaign($account);
        $delivery = $this->storeDelivery($campaign, $buyer, 'Weighted Store', 19, ['weight' => 100]);

        $this->makeConfig($campaign, [
            [
                'name' => 'Weighted tier',
                'mode' => 'weighted',
                'delivery_ids' => [$delivery->id],
            ],
        ]);

        $lead = $this->acceptedLead($campaign);
        $result = app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertTrue($result->sold);
        $this->assertDatabaseHas('delivery_logs', [
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'status' => 'success',
        ]);
    }

    public function test_all_tiers_fail_marks_lead_unsold_when_quarantine_disabled(): void
    {
        Http::fake(['https://fail.test/*' => Http::response(['Success' => false], 422)]);

        $account = $this->makeAccount();
        $buyer = $this->makeBuyer($account);
        $campaign = $this->makeCampaign($account, [
            'validation_config' => ['quarantine_unsold' => false],
        ]);
        $delivery = $this->directDelivery($campaign, $buyer, 'Always Fail', 'https://fail.test/post', 20);

        $this->makeConfig($campaign, [
            ['name' => 'Tier 1', 'mode' => 'waterfall', 'delivery_ids' => [$delivery->id]],
        ]);

        $lead = $this->acceptedLead($campaign);
        app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertSame(LeadStatus::Unsold, $lead->fresh()->status);
        $this->assertDatabaseHas('lead_events', [
            'lead_id' => $lead->id,
            'event_type' => 'lead.unsold',
        ]);
    }

    public function test_seeded_hybrid_ping_tree_sells_via_auction_or_store_fallback(): void
    {
        Http::fake([
            '*/api/v1/ping' => Http::response(['Success' => true, 'Cost' => 22, 'PingID' => 'seed'], 200),
            '*/api/v1/post' => Http::response(['Success' => true, 'Approved' => true], 200),
        ]);

        $campaign = Campaign::where('reference', 'loans-uk')->firstOrFail();
        $this->assertTrue($campaign->use_advanced_distribution);

        $lead = $this->acceptedLead($campaign, [
            'loan_amount' => 8000,
            'zipcode' => 'SW1A 1AA',
            'phone1' => '07700900555',
        ]);

        $result = app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertTrue($result->sold);
        $this->assertSame(LeadStatus::Sold, $lead->fresh()->status);
        $this->assertGreaterThanOrEqual(1, DeliveryLog::where('lead_id', $lead->id)->where('status', 'success')->count());
    }

    public function test_simulator_marks_ineligible_delivery_in_advanced_tier(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->firstOrFail();
        $delivery = $campaign->deliveries()->where('method', DeliveryMethod::PingPost)->firstOrFail();
        $delivery->buyer->update(['credit_balance' => 0]);
        $campaign->account->update([
            'settings' => array_merge($campaign->account->settings ?? [], ['require_buyer_prepay' => true]),
        ]);

        $result = app(RoutingSimulatorService::class)->simulate($campaign->fresh(), [
            'email' => 'sim@test.com',
            'firstname' => 'Sim',
            'lastname' => 'Test',
            'loan_amount' => 5000,
        ]);

        $this->assertSame('advanced', $result['mode']);
        $ineligible = collect($result['steps'])
            ->flatMap(fn ($tier) => $tier['deliveries'] ?? [])
            ->first(fn ($step) => ($step['delivery_id'] ?? null) === $delivery->id);

        $this->assertNotNull($ineligible);
        $this->assertFalse($ineligible['eligible']);
        $this->assertContains('Insufficient buyer credit', $ineligible['skip_reasons']);
    }

    public function test_delivery_log_tier_filter_scopes_to_ping_tree_tier(): void
    {
        $this->withoutVite();

        $admin = User::where('email', 'uk@powerbyexcellence.test')->firstOrFail();
        $campaign = Campaign::where('reference', 'loans-uk')->firstOrFail();
        $tierOneDelivery = Delivery::where('campaign_id', $campaign->id)->where('tier', 1)->firstOrFail();

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'tierlog@test.com'],
            'received_at' => now(),
        ]);

        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $tierOneDelivery->id,
            'buyer_id' => $tierOneDelivery->buyer_id,
            'status' => 'success',
            'ping_request' => ['test' => true],
            'duration_ms' => 40,
        ]);

        $otherDelivery = Delivery::where('campaign_id', $campaign->id)->where('tier', 2)->firstOrFail();
        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $otherDelivery->id,
            'buyer_id' => $otherDelivery->buyer_id,
            'status' => 'success',
            'duration_ms' => 50,
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->get(route('logs.delivery', ['tier' => 1, 'days' => 28]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.tier', '1')
                ->where('logs.total', 1)
                ->where('logs.data.0.delivery_id', $tierOneDelivery->id)
            );
    }

    public function test_reports_tier_summary_respects_campaign_filter(): void
    {
        $this->withoutVite();

        $admin = User::where('email', 'uk@powerbyexcellence.test')->firstOrFail();
        $campaign = Campaign::where('reference', 'loans-uk')->firstOrFail();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->get(route('reports.index', ['days' => 28, 'campaign_id' => $campaign->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('tierSummary.data')
                ->where('selectedCampaign.id', $campaign->id)
            );
    }

    /**
     * @param  callable(Campaign, Buyer): list<Delivery>  $tier1Deliveries
     * @param  callable(Campaign, Buyer): list<Delivery>  $tier2Deliveries
     * @return array{0: Campaign, 1: Delivery, 2: Delivery}
     */
    protected function twoTierTree(
        string $tier1Mode,
        callable $tier1Deliveries,
        callable $tier2Deliveries,
        ?float $tier1Floor = null,
        string $sellMode = 'exclusive',
    ): array {
        $account = $this->makeAccount();
        $buyer = $this->makeBuyer($account);
        $campaign = $this->makeCampaign($account, ['sell_mode' => $sellMode]);

        $tier1List = $tier1Deliveries($campaign, $buyer);
        $tier2List = $tier2Deliveries($campaign, $buyer);

        $groups = [
            [
                'name' => 'Tier 1',
                'mode' => $tier1Mode,
                'delivery_ids' => collect($tier1List)->pluck('id')->all(),
            ],
            [
                'name' => 'Tier 2',
                'mode' => 'waterfall',
                'delivery_ids' => collect($tier2List)->pluck('id')->all(),
            ],
        ];

        if ($tier1Floor !== null) {
            $groups[0]['floor_price'] = $tier1Floor;
        }

        $this->makeConfig($campaign, $groups);

        return [$campaign, $tier1List[0] ?? $tier2List[0], $tier2List[0]];
    }

    /**
     * @return array{0: Campaign, 1: Delivery, 2: Delivery}
     */
    protected function auctionTierCampaign(float $floor): array
    {
        $account = $this->makeAccount();
        $buyerHigh = $this->makeBuyer($account, 'buyer-high');
        $buyerLow = $this->makeBuyer($account, 'buyer-low');
        $campaign = $this->makeCampaign($account);

        $high = $this->pingDelivery(
            $campaign,
            $buyerHigh,
            'High Bidder',
            'https://high-bid.test/api/v1/ping',
            'https://high-bid.test/api/v1/post',
        );
        $low = $this->pingDelivery(
            $campaign,
            $buyerLow,
            'Low Bidder',
            'https://low-bid.test/api/v1/ping',
            'https://low-bid.test/api/v1/post',
        );

        $this->makeConfig($campaign, [
            [
                'name' => 'Auction',
                'mode' => 'parallel_auction',
                'floor_price' => $floor,
                'delivery_ids' => [$high->id, $low->id],
            ],
        ]);

        return [$campaign, $high, $low];
    }

    protected function makeAccount(): Account
    {
        return Account::create([
            'name' => 'Ping Tree Rigor',
            'slug' => 'ptr-'.uniqid(),
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
        ]);
    }

    protected function makeBuyer(Account $account, string $suffix = 'buyer'): Buyer
    {
        return Buyer::create([
            'account_id' => $account->id,
            'reference' => $suffix.'-'.uniqid(),
            'name' => ucfirst($suffix),
            'status' => 'active',
            'credit_balance' => 1000,
        ]);
    }

    protected function makeCampaign(Account $account, array $overrides = []): Campaign
    {
        return Campaign::create(array_merge([
            'account_id' => $account->id,
            'name' => 'Ping Tree Campaign',
            'reference' => 'ptr-campaign-'.uniqid(),
            'status' => 'active',
            'country' => 'GB',
            'currency' => 'GBP',
            'sell_mode' => 'exclusive',
            'payout_amount' => 5,
            'floor_price' => 10,
            'use_advanced_distribution' => true,
            'validation_config' => ['quarantine_unsold' => false],
        ], $overrides));
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     */
    protected function makeConfig(Campaign $campaign, array $groups): DistributionConfig
    {
        $campaign->distributionConfigs()->update(['is_active' => false]);

        return DistributionConfig::create([
            'campaign_id' => $campaign->id,
            'name' => 'Rigor tree',
            'is_active' => true,
            'config' => ['groups' => $groups],
        ]);
    }

    protected function directDelivery(
        Campaign $campaign,
        Buyer $buyer,
        string $name,
        string $url,
        float $revenue,
        array $overrides = [],
    ): Delivery {
        return Delivery::create(array_merge([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => $name,
            'method' => DeliveryMethod::DirectPost,
            'status' => 'active',
            'trigger_type' => 'on_lead_arrival',
            'priority' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => $revenue,
            'config' => ['url' => $url],
        ], $overrides));
    }

    protected function storeDelivery(
        Campaign $campaign,
        Buyer $buyer,
        string $name,
        float $revenue,
        array $overrides = [],
    ): Delivery {
        return Delivery::create(array_merge([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => $name,
            'method' => DeliveryMethod::StoreLead,
            'status' => 'active',
            'trigger_type' => 'on_lead_arrival',
            'priority' => 5,
            'revenue_type' => 'fixed',
            'revenue_amount' => $revenue,
        ], $overrides));
    }

    protected function pingDelivery(
        Campaign $campaign,
        Buyer $buyer,
        string $name,
        string $pingUrl,
        string $postUrl,
    ): Delivery {
        return Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => $name,
            'method' => DeliveryMethod::PingPost,
            'status' => 'active',
            'priority' => 1,
            'revenue_type' => 'dynamic',
            'revenue_amount' => 10,
            'advanced_distribution_only' => true,
            'config' => [
                'ping_url' => $pingUrl,
                'post_url' => $postUrl,
                'revenue_field' => 'Cost',
            ],
        ]);
    }

    protected function acceptedLead(Campaign $campaign, array $extraFields = []): Lead
    {
        return Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => array_merge([
                'firstname' => 'Ping',
                'lastname' => 'Tree',
                'email' => uniqid('ptr-', true).'@test.com',
                'phone1' => '07700900999',
                'zipcode' => 'SW1A 1AA',
            ], $extraFields),
            'received_at' => now(),
        ]);
    }
}
