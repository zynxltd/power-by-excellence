<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\CampaignField;
use App\Models\Delivery;
use App\Models\Lead;
use App\Services\Delivery\DeliveryExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadProcessingTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_post_respects_timeout_and_logs_duration(): void
    {
        Http::fake([
            'https://slow-buyer.test/*' => function () {
                usleep(100_000);

                return Http::response(['status' => 'ok'], 200);
            },
        ]);

        [$lead, $delivery] = $this->fixtures();

        $delivery->update([
            'method' => DeliveryMethod::DirectPost,
            'config' => [
                'url' => 'https://slow-buyer.test/leads',
                'timeout' => 5,
            ],
        ]);

        $result = app(DeliveryExecutor::class)->execute($lead, $delivery);

        $this->assertTrue($result->success);
        $log = $lead->deliveryLogs()->first();
        $this->assertNotNull($log);
        $this->assertGreaterThan(0, $log->duration_ms);
        $this->assertLessThan(5000, $log->duration_ms);
    }

    public function test_ping_post_timeout_fails_gracefully(): void
    {
        Http::fake([
            'https://timeout-buyer.test/ping' => Http::response(['Success' => false], 200),
        ]);

        [$lead, $delivery] = $this->fixtures();

        $delivery->update([
            'method' => DeliveryMethod::PingPost,
            'config' => [
                'ping_url' => 'https://timeout-buyer.test/ping',
                'post_url' => 'https://timeout-buyer.test/post',
                'ping_timeout' => 2,
                'timeout' => 5,
            ],
        ]);

        $result = app(DeliveryExecutor::class)->execute($lead, $delivery);

        $this->assertFalse($result->success);
        $this->assertDatabaseHas('delivery_logs', [
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
        ]);
    }

    public function test_sync_lead_processing_completes_under_threshold(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $key = \App\Models\ApiKey::first();
        $plain = 'test|secret';
        // Use seeded key via service - get token from seeder output is hard; create fresh
        $created = app(\App\Services\Api\ApiKeyService::class)->create([
            'account_id' => $key->account_id,
            'name' => 'Perf Test',
            'type' => 'administrator',
            'permissions' => ['leads.create'],
        ]);

        $start = microtime(true);

        $response = $this->postJson('/api/v1/leads', [
            'campaign_reference' => 'auto-insurance-uk',
            'sync' => true,
            'firstname' => 'Perf',
            'lastname' => 'Test',
            'email' => 'perf.'.uniqid().'@test.com',
            'phone1' => '07700900888',
            'zipcode' => 'SW1A 2AA',
            'sid' => 'google_search',
        ], ['Authorization' => 'Bearer '.$created['token']]);

        $elapsedMs = (microtime(true) - $start) * 1000;

        $response->assertOk();
        $this->assertLessThan(3000, $elapsedMs, "Sync processing took {$elapsedMs}ms — expected < 3000ms");
    }

    protected function fixtures(): array
    {
        $account = Account::create(['name' => 'T', 'slug' => 't', 'default_currency' => 'GBP', 'default_country' => 'GB']);
        $campaign = Campaign::create(['account_id' => $account->id, 'name' => 'C', 'reference' => 'c', 'floor_price' => 5]);
        foreach (['firstname', 'email', 'zipcode'] as $i => $name) {
            CampaignField::create(['campaign_id' => $campaign->id, 'name' => $name, 'required' => true, 'sort_order' => $i]);
        }
        $buyer = Buyer::create(['account_id' => $account->id, 'reference' => 'b', 'name' => 'B']);
        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'D',
            'method' => DeliveryMethod::StoreLead,
            'status' => 'active',
            'priority' => 1,
            'revenue_amount' => 10,
        ]);
        $lead = Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'field_data' => ['firstname' => 'A', 'email' => 'a@b.com', 'zipcode' => 'X1'],
        ]);

        return [$lead, $delivery];
    }
}
