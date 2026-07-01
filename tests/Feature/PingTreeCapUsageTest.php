<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CapCounter;
use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PingTreeCapUsageTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Route::get(
            '/distribution/{distribution}/cap-usage',
            [\App\Http\Controllers\Admin\DistributionController::class, 'capUsage'],
        )->middleware(['web', 'auth'])->name('distribution.cap-usage');

        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->firstOrFail();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_tier_reorder_persists_sort_order_in_config(): void
    {
        $campaign = Campaign::where('reference', 'auto-insurance-uk')->firstOrFail();
        $deliveries = Delivery::where('campaign_id', $campaign->id)->orderBy('id')->take(2)->get();
        $this->assertCount(2, $deliveries);

        $this->ukHost()
            ->actingAs($this->admin)
            ->post(route('distribution.store'), [
                'campaign_id' => $campaign->id,
                'name' => 'Reorder test tree',
                'is_active' => false,
                'groups' => [
                    [
                        'name' => 'First tier',
                        'mode' => 'waterfall',
                        'delivery_ids' => [$deliveries[0]->id],
                    ],
                    [
                        'name' => 'Second tier',
                        'mode' => 'waterfall',
                        'delivery_ids' => [$deliveries[1]->id],
                    ],
                ],
            ])
            ->assertRedirect();

        $config = DistributionConfig::where('name', 'Reorder test tree')->firstOrFail();
        $this->assertSame('First tier', $config->config['groups'][0]['name']);
        $this->assertSame(0, $config->config['groups'][0]['sort_order']);
        $this->assertSame(1, $config->config['groups'][1]['sort_order']);

        $this->ukHost()
            ->actingAs($this->admin)
            ->put(route('distribution.update', $config), [
                'campaign_id' => $campaign->id,
                'name' => 'Reorder test tree',
                'is_active' => false,
                'groups' => [
                    [
                        'name' => 'Second tier',
                        'mode' => 'waterfall',
                        'delivery_ids' => [$deliveries[1]->id],
                    ],
                    [
                        'name' => 'First tier',
                        'mode' => 'waterfall',
                        'delivery_ids' => [$deliveries[0]->id],
                    ],
                ],
            ])
            ->assertRedirect(route('distribution.show', $config));

        $config->refresh();
        $groups = $config->config['groups'];

        $this->assertSame('Second tier', $groups[0]['name']);
        $this->assertSame(0, $groups[0]['sort_order']);
        $this->assertSame('First tier', $groups[1]['name']);
        $this->assertSame(1, $groups[1]['sort_order']);
    }

    public function test_cap_usage_endpoint_returns_daily_usage_for_tier_deliveries(): void
    {
        $campaign = Campaign::where('reference', 'auto-insurance-uk')->firstOrFail();
        $delivery = Delivery::where('campaign_id', $campaign->id)->firstOrFail();
        $delivery->update(['caps' => ['daily' => 50]]);

        CapCounter::create([
            'entity_type' => 'delivery',
            'entity_id' => $delivery->id,
            'period' => 'daily',
            'period_key' => now()->format('Y-m-d'),
            'count' => 12,
            'spend' => 0,
            'reset_at' => now()->endOfDay(),
        ]);

        $config = DistributionConfig::where('campaign_id', $campaign->id)->firstOrFail();

        $this->ukHost()
            ->actingAs($this->admin)
            ->getJson('/distribution/'.$config->id.'/cap-usage')
            ->assertOk()
            ->assertJsonPath("cap_usage.{$delivery->id}.daily.used", 12)
            ->assertJsonPath("cap_usage.{$delivery->id}.daily.limit", 50);

        $uncapped = Delivery::where('campaign_id', $campaign->id)
            ->where('id', '!=', $delivery->id)
            ->first();

        if ($uncapped) {
            $uncapped->update(['caps' => null]);

            $this->ukHost()
                ->actingAs($this->admin)
                ->getJson('/distribution/'.$config->id.'/cap-usage')
                ->assertOk()
                ->assertJsonPath("cap_usage.{$uncapped->id}.daily.limit", null);
        }
    }
}
