<?php

namespace Tests\Feature;

use App\Enums\CallStatus;
use App\Enums\DeliveryMethod;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\BuyerTransaction;
use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\User;
use App\Services\Calls\CallBillingService;
use App\Services\Calls\CallRouter;
use App\Support\CallLogic\CallLogicRouteRegistrar;
use App\Support\Products\CallLogicProduct;
use App\Support\Tenancy\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPerCallBillingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

    protected Buyer $buyer;

    protected function setUp(): void
    {
        $this->afterApplicationCreated(function (): void {
            CallLogicRouteRegistrar::register();
        });

        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->account = $this->admin->account;
        CallLogicProduct::enable($this->account);

        $settings = $this->account->settings ?? [];
        $settings['require_buyer_prepay'] = true;
        $this->account->update(['settings' => $settings]);

        $this->buyer = Buyer::where('account_id', $this->account->id)->first();
        $this->buyer->update(['credit_balance' => 100]);
        $this->account->refresh();
    }

    public function test_sold_call_debits_buyer_on_route(): void
    {
        Http::fake([
            '*/api/v1/mock/call-buyers/1/ping' => Http::response([
                'Success' => true,
                'Cost' => 25,
                'PingID' => 'call_bill_ping',
            ]),
        ]);

        $campaign = $this->createCallCampaign();
        $delivery = $this->createCallDelivery($campaign, 25);

        $session = CallSession::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'status' => CallStatus::Routing,
            'caller_number' => '+447700900123',
            'min_duration_seconds' => 30,
        ]);

        AccountContext::set($this->account);
        $result = app(CallRouter::class)->route($session->fresh());

        $this->assertTrue($result->success);
        $session->refresh();

        $this->assertNotNull($session->billed_at);
        $this->assertSame('25.00', $session->billed_amount);
        $this->assertNotNull($session->buyer_transaction_id);
        $this->assertEquals(75, (float) $this->buyer->fresh()->credit_balance);

        $transaction = BuyerTransaction::find($session->buyer_transaction_id);
        $this->assertNotNull($transaction);
        $this->assertSame($session->id, $transaction->meta['call_session_id']);
        $this->assertSame($session->uuid, $transaction->meta['call_session_uuid']);
    }

    public function test_insufficient_credit_blocks_call_sale(): void
    {
        $this->buyer->update(['credit_balance' => 5]);

        Http::fake([
            '*/api/v1/mock/call-buyers/1/ping' => Http::response([
                'Success' => true,
                'Cost' => 25,
                'PingID' => 'call_bill_block',
            ]),
        ]);

        $campaign = $this->createCallCampaign();
        $this->createCallDelivery($campaign, 25);

        $session = CallSession::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'status' => CallStatus::Routing,
            'caller_number' => '+447700900123',
            'min_duration_seconds' => 30,
        ]);

        AccountContext::set($this->account);
        $result = app(CallRouter::class)->route($session->fresh());

        $this->assertFalse($result->success);
        $session->refresh();
        $this->assertNull($session->sold_to_buyer_id);
        $this->assertNull($session->billed_at);
        $this->assertEquals(5, (float) $this->buyer->fresh()->credit_balance);
    }

    public function test_duplicate_bill_attempt_is_idempotent(): void
    {
        $campaign = $this->createCallCampaign();
        $delivery = $this->createCallDelivery($campaign, 20);

        $session = CallSession::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'status' => CallStatus::Transferring,
            'caller_number' => '+447700900123',
            'sold_to_buyer_id' => $this->buyer->id,
            'winning_delivery_id' => $delivery->id,
            'revenue' => 20,
        ]);

        $billing = app(CallBillingService::class);
        $first = $billing->billSoldCall($session, $delivery, 20);
        $second = $billing->billSoldCall($session->fresh(), $delivery, 20);

        $this->assertTrue($first->success);
        $this->assertFalse($first->alreadyBilled);
        $this->assertTrue($second->success);
        $this->assertTrue($second->alreadyBilled);
        $this->assertEquals(80, (float) $this->buyer->fresh()->credit_balance);
        $this->assertSame(1, BuyerTransaction::where('buyer_id', $this->buyer->id)
            ->where('description', 'Call purchase')
            ->count());
    }

    public function test_call_show_displays_billing_status(): void
    {
        $campaign = $this->createCallCampaign();
        $delivery = $this->createCallDelivery($campaign, 15);

        $session = CallSession::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'status' => CallStatus::Completed,
            'caller_number' => '+447700900999',
            'sold_to_buyer_id' => $this->buyer->id,
            'winning_delivery_id' => $delivery->id,
            'revenue' => 15,
            'billed_at' => now(),
            'billed_amount' => 15,
        ]);

        $this->actingAs($this->admin)
            ->get(route('call-logic.calls.show', $session->uuid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/CallLogic/Calls/Show')
                ->where('call.billed_amount', fn ($v) => (float) $v === 15.0)
                ->where('call.billed_at', fn ($v) => $v !== null));
    }

    public function test_default_per_call_price_fallback_is_used(): void
    {
        $settings = $this->account->settings ?? [];
        $settings['call_logic'] = array_merge($settings['call_logic'] ?? [], [
            'default_per_call_price' => 12.5,
        ]);
        $this->account->update(['settings' => $settings]);

        $campaign = $this->createCallCampaign();
        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $this->buyer->id,
            'name' => 'No fixed rate',
            'method' => DeliveryMethod::CallDirectTransfer,
            'trigger_type' => 'on_lead_arrival',
            'status' => 'active',
            'priority' => 1,
            'tier' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => 0,
            'config' => ['destination_phone' => '+441234567890'],
        ]);

        $session = CallSession::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'status' => CallStatus::Routing,
            'caller_number' => '+447700900123',
        ]);

        $price = app(CallBillingService::class)->resolveCallPrice($session, $delivery);

        $this->assertSame(12.5, $price);
    }

    protected function createCallCampaign(array $overrides = []): Campaign
    {
        return Campaign::create(array_merge([
            'account_id' => $this->account->id,
            'type' => 'standard',
            'channel' => 'call',
            'name' => 'Call Billing Campaign',
            'reference' => 'call-bill-'.uniqid(),
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'floor_price' => 10,
            'ping_timeout_ms' => 1500,
            'call_settings' => ['routing_mode' => 'waterfall', 'min_duration_seconds' => 30],
        ], $overrides));
    }

    protected function createCallDelivery(Campaign $campaign, float $amount): Delivery
    {
        return Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $this->buyer->id,
            'name' => 'Call buyer tier 1',
            'method' => DeliveryMethod::CallPingPost,
            'trigger_type' => 'on_lead_arrival',
            'status' => 'active',
            'priority' => 1,
            'tier' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => $amount,
            'config' => [
                'ping_url' => url('/api/v1/mock/call-buyers/1/ping'),
                'destination_phone' => '+441234567890',
            ],
        ]);
    }
}
