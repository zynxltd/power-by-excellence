<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignShowTest extends TestCase
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

    public function test_campaign_show_includes_all_campaign_fields(): void
    {
        $campaign = Campaign::first();

        foreach (range(1, 30) as $i) {
            $campaign->fields()->create([
                'name' => "extra_field_{$i}",
                'label' => "Extra Field {$i}",
                'type' => 'text',
                'required' => $i <= 3,
                'ping_field' => $i % 5 === 0,
                'sort_order' => 100 + $i,
            ]);
        }

        $expectedCount = $campaign->fields()->count();

        $this->actingAs($this->admin)
            ->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('campaign.fields', fn ($fields) => count($fields) === $expectedCount)
            );
    }

    public function test_campaign_show_includes_paginated_deliveries(): void
    {
        $campaign = Campaign::first();
        $buyer = $campaign->account->buyers()->first();
        $existing = $campaign->deliveries()->count();

        foreach (range(1, 20) as $i) {
            Delivery::create([
                'campaign_id' => $campaign->id,
                'buyer_id' => $buyer->id,
                'name' => "Pagination Test Delivery {$i}",
                'method' => 'ping_post',
                'status' => 'active',
                'priority' => $i,
                'tier' => (int) ceil($i / 5),
            ]);
        }

        $total = $existing + 20;

        $this->actingAs($this->admin)
            ->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('deliveries.data', 15)
                ->where('deliveries.total', $total)
                ->has('campaign.distribution_configs')
            );

        $lastPage = (int) ceil($total / 15);

        $this->actingAs($this->admin)
            ->get(route('campaigns.show', $campaign).'?delivery_page='.$lastPage)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('deliveries.current_page', $lastPage)
                ->where('deliveries.total', $total)
            );
    }
}
