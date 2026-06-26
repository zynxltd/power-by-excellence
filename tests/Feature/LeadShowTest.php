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

class LeadShowTest extends TestCase
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

    public function test_lead_show_includes_delivery_logs_with_ping_post_payloads(): void
    {
        $campaign = Campaign::first();
        $buyer = Buyer::first();

        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Test Ping Route',
            'method' => 'ping_post',
            'status' => 'active',
            'tier' => 2,
        ]);

        $lead = Lead::create([
            'campaign_id' => $campaign->id,
            'account_id' => $campaign->account_id,
            'status' => 'unsold',
            'field_data' => ['email' => 'test@example.com'],
            'received_at' => now(),
        ]);

        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $buyer->id,
            'status' => 'failed',
            'duration_ms' => 420,
            'http_status' => 422,
            'ping_request' => ['url' => 'https://buyer.test/ping', 'body' => ['email' => 'test@example.com']],
            'ping_response' => ['accepted' => false, 'price' => 0],
            'post_request' => ['url' => 'https://buyer.test/post', 'body' => ['email' => 'test@example.com']],
            'post_response' => ['error' => 'rejected'],
        ]);

        $this->actingAs($this->admin)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Leads/Show')
                ->has('lead.delivery_logs', 1)
                ->where('lead.delivery_logs.0.ping_request.url', 'https://buyer.test/ping')
                ->where('lead.delivery_logs.0.post_response.error', 'rejected')
                ->where('lead.delivery_logs.0.delivery.tier', 2)
                ->has('leadQuality.score')
                ->has('leadQuality.email.label')
                ->has('leadQuality.hlr.label')
            );
    }
}
