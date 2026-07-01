<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\CampaignField;
use App\Models\CampaignSupplier;
use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Models\Lead;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Campaigns\CampaignCloneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignCloneTest extends TestCase
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

    /**
     * @return array{0: Campaign, 1: list<Delivery>, 2: DistributionConfig}
     */
    protected function sourceCampaignWithTree(): array
    {
        $buyer = Buyer::where('account_id', $this->ukAccount->id)->firstOrFail();

        $campaign = Campaign::create([
            'account_id' => $this->ukAccount->id,
            'name' => 'Clone Source Campaign',
            'reference' => 'clone-source-'.uniqid(),
            'status' => 'active',
            'country' => 'GB',
            'currency' => 'GBP',
            'payout_amount' => 8,
            'floor_price' => 15,
            'use_advanced_distribution' => true,
            'reference_locked' => true,
            'caps' => ['daily' => 50],
            'dedupe_config' => ['fields' => ['email'], 'reject_days' => 30],
            'validation_config' => ['require_email' => true],
            'api_spec' => ['fields' => [['name' => 'email']]],
            'call_settings' => ['record_calls' => true],
        ]);

        CampaignField::create([
            'campaign_id' => $campaign->id,
            'name' => 'email',
            'label' => 'Email',
            'required' => true,
            'ping_field' => false,
            'sort_order' => 0,
        ]);

        CampaignField::create([
            'campaign_id' => $campaign->id,
            'name' => 'zipcode',
            'label' => 'Postcode',
            'required' => true,
            'ping_field' => true,
            'sort_order' => 1,
        ]);

        $deliveryA = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Tier 1 Buyer A',
            'method' => DeliveryMethod::DirectPost,
            'status' => 'active',
            'priority' => 1,
            'tier' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => 20,
            'config' => ['url' => 'https://buyer-a.test/post'],
        ]);

        $deliveryB = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Tier 2 Buyer B',
            'method' => DeliveryMethod::PingPost,
            'status' => 'active',
            'priority' => 2,
            'tier' => 2,
            'revenue_type' => 'dynamic',
            'revenue_amount' => 10,
            'config' => [
                'ping_url' => 'https://buyer-b.test/ping',
                'post_url' => 'https://buyer-b.test/post',
            ],
        ]);

        $config = DistributionConfig::create([
            'campaign_id' => $campaign->id,
            'name' => 'Primary tree',
            'is_active' => true,
            'config' => [
                'decline_url' => 'https://example.test/decline',
                'groups' => [
                    [
                        'name' => 'Tier 1',
                        'mode' => 'waterfall',
                        'sort_order' => 0,
                        'delivery_ids' => [$deliveryA->id],
                    ],
                    [
                        'name' => 'Tier 2',
                        'mode' => 'parallel_auction',
                        'sort_order' => 1,
                        'delivery_ids' => [$deliveryB->id],
                    ],
                ],
            ],
        ]);

        $supplier = Supplier::where('account_id', $this->ukAccount->id)->firstOrFail();
        CampaignSupplier::create([
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplier->id,
        ]);

        Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => ['email' => 'source@example.com'],
            'received_at' => now(),
        ]);

        return [$campaign, [$deliveryA, $deliveryB], $config];
    }

    public function test_clone_copies_delivery_count_and_distribution_tiers(): void
    {
        [$source, $deliveries, $sourceConfig] = $this->sourceCampaignWithTree();

        $copy = app(CampaignCloneService::class)->clone($source);

        $this->assertNotSame($source->id, $copy->id);
        $this->assertSame('saved', $copy->status);
        $this->assertSame($source->name.' (copy)', $copy->name);
        $this->assertStringStartsWith($source->reference.'-copy', $copy->reference);
        $this->assertFalse($copy->reference_locked);

        $this->assertSame(2, CampaignField::where('campaign_id', $copy->id)->count());
        $this->assertSame(2, Delivery::where('campaign_id', $copy->id)->count());
        $this->assertSame(1, DistributionConfig::where('campaign_id', $copy->id)->count());
        $this->assertSame(0, Lead::where('campaign_id', $copy->id)->count());
        $this->assertSame(0, CampaignSupplier::where('campaign_id', $copy->id)->count());

        $this->assertEquals($source->dedupe_config, $copy->dedupe_config);
        $this->assertEquals($source->validation_config, $copy->validation_config);
        $this->assertEquals($source->api_spec, $copy->api_spec);
        $this->assertEquals($source->call_settings, $copy->call_settings);
        $this->assertEquals($source->caps, $copy->caps);

        $copyDeliveries = Delivery::where('campaign_id', $copy->id)->orderBy('priority')->get();
        $this->assertTrue($copyDeliveries->every(fn (Delivery $d) => $d->status === 'saved'));
        $this->assertSame($deliveries[0]->buyer_id, $copyDeliveries[0]->buyer_id);
        $this->assertSame($deliveries[1]->buyer_id, $copyDeliveries[1]->buyer_id);

        $copyConfig = DistributionConfig::where('campaign_id', $copy->id)->firstOrFail();
        $groups = $copyConfig->config['groups'];

        $this->assertCount(2, $groups);
        $this->assertSame('Tier 1', $groups[0]['name']);
        $this->assertSame('Tier 2', $groups[1]['name']);
        $this->assertSame('waterfall', $groups[0]['mode']);
        $this->assertSame('parallel_auction', $groups[1]['mode']);
        $this->assertSame([$copyDeliveries[0]->id], $groups[0]['delivery_ids']);
        $this->assertSame([$copyDeliveries[1]->id], $groups[1]['delivery_ids']);
        $this->assertNotSame($sourceConfig->id, $copyConfig->id);
        $this->assertNotSame($deliveries[0]->id, $copyDeliveries[0]->id);
    }

    public function test_clone_edits_do_not_affect_source(): void
    {
        [$source, $deliveries] = $this->sourceCampaignWithTree();

        $copy = app(CampaignCloneService::class)->clone($source, 'Edited Clone', 'edited-clone-'.uniqid());

        $sourceDelivery = $deliveries[0]->fresh();
        $copyDelivery = Delivery::where('campaign_id', $copy->id)->orderBy('priority')->firstOrFail();

        $copyDelivery->update(['name' => 'Clone-only delivery name']);

        $source->refresh();
        $sourceDelivery->refresh();

        $this->assertSame('Clone Source Campaign', $source->name);
        $this->assertSame('Tier 1 Buyer A', $sourceDelivery->name);
        $this->assertSame('Clone-only delivery name', $copyDelivery->fresh()->name);
    }

    public function test_campaign_clone_endpoint_accepts_custom_name_and_reference(): void
    {
        [$source] = $this->sourceCampaignWithTree();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('campaigns.clone', $source), [
                'name' => 'HTTP Cloned Campaign',
                'reference' => 'http-cloned-campaign',
            ])
            ->assertRedirect();

        $copy = Campaign::where('reference', 'http-cloned-campaign')->firstOrFail();

        $this->assertSame('HTTP Cloned Campaign', $copy->name);
        $this->assertSame('saved', $copy->status);
        $this->assertSame(2, Delivery::where('campaign_id', $copy->id)->count());
    }
}
