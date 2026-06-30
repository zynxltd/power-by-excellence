<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\CapCounter;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Models\User;
use App\Services\Caps\CapService;
use App\Services\Delivery\DeliveryTestHarnessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryTestHarnessTest extends TestCase
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
     * @return array{0: Campaign, 1: Delivery}
     */
    protected function pingPostDelivery(): array
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->firstOrFail();
        $campaign->update(['floor_price' => 10]);

        $buyer = Buyer::where('account_id', $this->ukAccount->id)->firstOrFail();

        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Harness ping-post',
            'method' => DeliveryMethod::PingPost,
            'status' => 'active',
            'trigger_type' => 'on_lead_arrival',
            'priority' => 1,
            'revenue_type' => 'dynamic',
            'revenue_amount' => 10,
            'config' => [
                'ping_url' => 'https://harness-buyer.test/ping',
                'post_url' => 'https://harness-buyer.test/post',
                'revenue_field' => 'Cost',
            ],
        ]);

        return [$campaign, $delivery];
    }

    public function test_ping_post_accept_mode_returns_sold(): void
    {
        [, $delivery] = $this->pingPostDelivery();

        $result = app(DeliveryTestHarnessService::class)->run($delivery, DeliveryTestHarnessService::MODE_ACCEPT);

        $this->assertTrue($result['sold']);
        $this->assertSame('sold', $result['outcome']);
        $this->assertSame(200, $result['http_status']);

        $log = DeliveryLog::findOrFail($result['log_id']);
        $this->assertSame('success', $log->status);
        $this->assertTrue(data_get($log->ping_request, '_meta.is_test'));
        $this->assertSame('accept', data_get($log->ping_request, '_meta.mode'));

        $lead = Lead::findOrFail($result['lead_id']);
        $this->assertTrue($lead->metadata['is_test']);
    }

    public function test_reject_mode_is_not_sold(): void
    {
        [, $delivery] = $this->pingPostDelivery();

        $result = app(DeliveryTestHarnessService::class)->run($delivery, DeliveryTestHarnessService::MODE_REJECT);

        $this->assertFalse($result['sold']);
        $this->assertSame('reject', $result['outcome']);
        $this->assertSame('ping_rejected', $result['skip_reason']);
    }

    public function test_timeout_mode_is_handled_without_success(): void
    {
        [, $delivery] = $this->pingPostDelivery();

        $result = app(DeliveryTestHarnessService::class)->run($delivery, DeliveryTestHarnessService::MODE_TIMEOUT);

        $this->assertFalse($result['sold']);
        $this->assertSame('timeout', $result['outcome']);
    }

    public function test_harness_does_not_increment_buyer_or_delivery_caps(): void
    {
        [, $delivery] = $this->pingPostDelivery();
        $buyer = $delivery->buyer;

        $buyer->update(['caps' => ['daily' => 5]]);
        $delivery->update(['caps' => ['daily' => 5]]);

        app(CapService::class)->increment('buyer', $buyer->id, $buyer->caps);
        app(CapService::class)->increment('delivery', $delivery->id, $delivery->caps);

        $buyerCountBefore = (int) CapCounter::query()
            ->where('entity_type', 'buyer')
            ->where('entity_id', $buyer->id)
            ->sum('count');

        $deliveryCountBefore = (int) CapCounter::query()
            ->where('entity_type', 'delivery')
            ->where('entity_id', $delivery->id)
            ->sum('count');

        $result = app(DeliveryTestHarnessService::class)->run($delivery, DeliveryTestHarnessService::MODE_ACCEPT);

        $this->assertTrue($result['sold']);

        $buyerCountAfter = (int) CapCounter::query()
            ->where('entity_type', 'buyer')
            ->where('entity_id', $buyer->id)
            ->sum('count');

        $deliveryCountAfter = (int) CapCounter::query()
            ->where('entity_type', 'delivery')
            ->where('entity_id', $delivery->id)
            ->sum('count');

        $this->assertSame($buyerCountBefore, $buyerCountAfter);
        $this->assertSame($deliveryCountBefore, $deliveryCountAfter);
    }

    public function test_deliveries_test_endpoint_returns_json_result(): void
    {
        [, $delivery] = $this->pingPostDelivery();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->postJson(route('deliveries.test', $delivery), [
                'mode' => DeliveryTestHarnessService::MODE_ACCEPT,
            ])
            ->assertOk()
            ->assertJsonPath('test_result.sold', true)
            ->assertJsonPath('test_result.outcome', 'sold')
            ->assertJsonPath('test_result.mode', 'accept');
    }
}
