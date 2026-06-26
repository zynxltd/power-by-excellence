<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Services\Demo\LargePingTreeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LargePingTreeBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_thirty_five_tier_tree_with_varied_deliveries(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $account = Account::where('slug', 'emea-loans')->firstOrFail();
        $campaign = Campaign::where('account_id', $account->id)->where('reference', 'auto-emea')->firstOrFail();

        $distribution = DistributionConfig::create([
            'campaign_id' => $campaign->id,
            'name' => 'Test large tree',
            'is_active' => true,
            'config' => ['groups' => []],
        ]);

        $result = app(LargePingTreeBuilder::class)->build($campaign, $account, $distribution, 35);

        $this->assertSame(35, $result['tiers']);
        $this->assertGreaterThanOrEqual(38, $result['deliveries']);
        $this->assertGreaterThanOrEqual(35, $result['buyers']);

        $distribution->refresh();
        $this->assertCount(35, $distribution->config['groups']);
        $this->assertGreaterThanOrEqual(38, Delivery::where('campaign_id', $campaign->id)->count());

        $methods = Delivery::where('campaign_id', $campaign->id)->pluck('method')->map->value->unique()->values()->all();
        $this->assertContains('ping_post', $methods);
        $this->assertContains('direct_post', $methods);
        $this->assertContains('store_lead', $methods);
    }
}
