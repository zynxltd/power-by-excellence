<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadDeliveryLogsRigorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_rejected_lead_without_deliveries_shows_empty_delivery_logs(): void
    {
        $campaign = Campaign::first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => 'rejected',
            'reject_reason' => 'Campaign cap reached',
            'field_data' => ['email' => 'rejected@test.com'],
            'received_at' => now(),
        ]);

        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->actingAs($admin)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('lead.status', 'rejected')
                ->where('lead.delivery_logs', [])
                ->where('outcomeDetail.title', 'Rejected')
            );
    }

    public function test_delivery_logs_sorted_by_tier_in_ui_payload(): void
    {
        $campaign = Campaign::first();
        $buyer = Buyer::first();

        $tier3 = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Tier 3 Route',
            'method' => 'ping_post',
            'status' => 'active',
            'tier' => 3,
        ]);

        $tier1 = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Tier 1 Route',
            'method' => 'ping_post',
            'status' => 'active',
            'tier' => 1,
        ]);

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => 'unsold',
            'field_data' => ['email' => 'tier@test.com'],
            'received_at' => now(),
        ]);

        foreach ([$tier3, $tier1] as $delivery) {
            DeliveryLog::create([
                'lead_id' => $lead->id,
                'delivery_id' => $delivery->id,
                'buyer_id' => $buyer->id,
                'status' => 'failed',
                'ping_request' => ['tier' => $delivery->tier],
                'ping_response' => ['accepted' => false],
            ]);
        }

        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $response = $this->actingAs($admin)
            ->get(route('leads.show', $lead))
            ->assertOk();

        $logs = $response->viewData('page')['props']['lead']['delivery_logs'];
        $this->assertCount(2, $logs);
        $this->assertSame(1, $logs[0]['delivery']['tier']);
        $this->assertSame(3, $logs[1]['delivery']['tier']);
    }

    public function test_skipped_delivery_includes_reason_in_payload(): void
    {
        $campaign = Campaign::first();
        $buyer = Buyer::first();
        $delivery = Delivery::where('campaign_id', $campaign->id)->first();

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => 'unsold',
            'field_data' => ['email' => 'skip@test.com'],
            'received_at' => now(),
        ]);

        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $buyer->id,
            'status' => 'skipped',
            'skipped_reason' => 'Buyer cap reached',
            'ping_request' => ['url' => '/ping'],
            'ping_response' => null,
        ]);

        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->actingAs($admin)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('lead.delivery_logs.0.status', 'skipped')
                ->where('lead.delivery_logs.0.skipped_reason', 'Buyer cap reached')
                ->where('lead.delivery_logs.0.ping_request.url', '/ping')
            );
    }
}
