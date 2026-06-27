<?php

namespace Tests\Feature;

use App\Models\AccountAuditLog;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
    }

    public function test_distribution_config_crud(): void
    {
        $this->actingAs($this->admin);

        $campaign = Campaign::where('reference', 'auto-insurance-uk')->first();
        $delivery = Delivery::where('campaign_id', $campaign->id)->first();

        $response = $this->post(route('distribution.store'), [
            'campaign_id' => $campaign->id,
            'name' => 'Test Ping Tree',
            'is_active' => true,
            'groups' => [
                [
                    'name' => 'Tier 1',
                    'mode' => 'waterfall',
                    'delivery_ids' => [$delivery->id],
                ],
            ],
        ]);

        $config = DistributionConfig::where('name', 'Test Ping Tree')->first();
        $this->assertNotNull($config);
        $response->assertRedirect(route('distribution.show', $config));

        $this->put(route('distribution.update', $config), [
            'campaign_id' => $campaign->id,
            'name' => 'Updated Ping Tree',
            'is_active' => true,
            'decline_url' => 'https://example.com/declined',
            'groups' => [
                [
                    'name' => 'Tier 1 Auction',
                    'mode' => 'parallel_auction',
                    'floor_price' => 10,
                    'redirect_url' => 'https://example.com/tier-thanks',
                    'delivery_ids' => [$delivery->id],
                ],
            ],
        ])->assertRedirect(route('distribution.show', $config));

        $config->refresh();
        $this->assertSame('Updated Ping Tree', $config->name);
        $this->assertSame('parallel_auction', $config->config['groups'][0]['mode']);
        $this->assertSame('https://example.com/tier-thanks', $config->config['groups'][0]['redirect_url']);
        $this->assertSame('https://example.com/declined', $config->config['decline_url']);

        $this->assertDatabaseHas('account_audit_logs', [
            'action' => 'distribution_config.updated',
            'entity_type' => 'distribution_config',
            'entity_id' => $config->id,
        ]);

        $audit = AccountAuditLog::where('entity_id', $config->id)
            ->where('action', 'distribution_config.updated')
            ->first();

        $this->assertSame('Updated Ping Tree', $audit->changes['after']['name'] ?? null);

        $this->delete(route('distribution.destroy', $config))->assertRedirect(route('distribution.index'));
        $this->assertNull($config->fresh());
    }

    public function test_locked_ping_tree_blocks_update_and_delete(): void
    {
        $this->actingAs($this->admin);

        $campaign = Campaign::where('reference', 'auto-insurance-uk')->first();
        $delivery = Delivery::where('campaign_id', $campaign->id)->first();
        $config = DistributionConfig::where('campaign_id', $campaign->id)->first();

        $this->post(route('distribution.lock', $config), ['locked' => true])
            ->assertRedirect();

        $this->assertTrue($config->fresh()->is_locked);

        $this->put(route('distribution.update', $config), [
            'campaign_id' => $campaign->id,
            'name' => 'Blocked rename',
            'is_active' => true,
            'groups' => [
                [
                    'name' => 'Tier 1',
                    'mode' => 'waterfall',
                    'delivery_ids' => [$delivery->id],
                ],
            ],
        ])->assertStatus(422);

        $this->delete(route('distribution.destroy', $config))->assertStatus(422);

        $this->post(route('distribution.lock', $config), ['locked' => false])
            ->assertRedirect();

        $this->assertFalse($config->fresh()->is_locked);
    }

    public function test_billing_top_up_creates_ledger_entry(): void
    {
        $this->actingAs($this->admin);

        $buyer = \App\Models\Buyer::where('reference', 'buyer-primary')->first();
        $before = (float) $buyer->credit_balance;

        $this->post(route('billing.top-up', $buyer), [
            'amount' => 50,
            'description' => 'Test top-up',
        ])->assertRedirect();

        $buyer->refresh();
        $this->assertEquals($before + 50, (float) $buyer->credit_balance);
        $this->assertDatabaseHas('buyer_transactions', [
            'buyer_id' => $buyer->id,
            'type' => 'credit',
            'description' => 'Test top-up',
        ]);
    }
}
