<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Services\Platform\TenantHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_health_is_healthy_when_post_success_and_sold_rate_are_good(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first()
            ?? Campaign::query()->first();
        $this->assertNotNull($campaign);
        $delivery = Delivery::where('campaign_id', $campaign->id)->first();
        $accountId = $campaign->account_id;

        for ($i = 0; $i < 8; $i++) {
            $lead = Lead::create([
                'account_id' => $accountId,
                'campaign_id' => $campaign->id,
                'status' => 'sold',
                'field_data' => ['email' => "p{$i}@test.test"],
                'received_at' => now(),
                'distributed_at' => now(),
                'processing_ms' => 120,
            ]);

            DeliveryLog::create([
                'lead_id' => $lead->id,
                'delivery_id' => $delivery->id,
                'status' => 'success',
                'ping_request' => ['tier' => 1],
                'post_request' => ['tier' => 1],
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            $lead = Lead::create([
                'account_id' => $accountId,
                'campaign_id' => $campaign->id,
                'status' => 'unsold',
                'field_data' => ['email' => "f{$i}@test.test"],
                'received_at' => now(),
            ]);

            DeliveryLog::create([
                'lead_id' => $lead->id,
                'delivery_id' => $delivery->id,
                'status' => 'failed',
                'ping_request' => ['tier' => 1],
            ]);
        }

        $this->assertSame('healthy', app(TenantHealth::class)->status($accountId));
    }
}
