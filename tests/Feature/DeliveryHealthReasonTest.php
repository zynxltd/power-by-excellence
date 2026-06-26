<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Services\Delivery\DeliveryAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeliveryHealthReasonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_health_reason_explains_low_success_rate(): void
    {
        $campaign = Campaign::where('reference', 'auto-insurance-uk')->first();
        $campaign->load('account');
        $buyer = Buyer::where('account_id', $campaign->account_id)->first();
        $lead = $campaign->leads()->first();

        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Health reason QA',
            'method' => 'store_lead',
            'status' => 'active',
            'revenue_type' => 'fixed',
            'revenue_amount' => 10,
        ]);

        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $buyer->id,
            'status' => 'success',
            'created_at' => now()->subHour(),
        ]);
        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $buyer->id,
            'status' => 'failed',
            'created_at' => now()->subMinutes(45),
        ]);
        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $buyer->id,
            'status' => 'failed',
            'created_at' => now()->subMinutes(30),
        ]);

        $detail = app(DeliveryAnalyticsService::class)->healthDetailFor($delivery->fresh(['campaign.account', 'buyer']));

        $this->assertSame('warning', $detail['health']);
        $this->assertStringContainsString('Low success rate', $detail['health_reason']);
        $this->assertNotNull($detail['platform_name']);
    }

    public function test_deliveries_table_includes_health_reason(): void
    {
        $admin = \App\Models\User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->get(route('deliveries.index', ['view' => 'table']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('deliveries.data.0.health_reason')
                ->has('deliveries.data.0.platform_name')
            );
    }
}
