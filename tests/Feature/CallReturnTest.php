<?php

namespace Tests\Feature;

use App\Enums\CallStatus;
use App\Enums\DeliveryMethod;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\BuyerTransaction;
use App\Models\CallReturn;
use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\User;
use App\Support\CallLogic\CallLogicRouteRegistrar;
use App\Support\Products\CallLogicProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallReturnTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $buyerUser;

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

        $this->buyer = Buyer::where('account_id', $this->account->id)->first();
        $this->buyer->update(['credit_balance' => 50]);
        $this->buyerUser = User::where('email', 'buyer-portal@excellence-uk.test')->firstOrFail();
    }

    protected function host()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_buyer_can_submit_return_for_billed_call(): void
    {
        $session = $this->billedCallSession();

        $this->host()
            ->actingAs($this->buyerUser)
            ->post(route('portal.buyer.calls.return', $session->uuid), [
                'reason' => 'Wrong number',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('call_returns', [
            'call_session_id' => $session->id,
            'buyer_id' => $this->buyer->id,
            'status' => 'pending',
            'reason' => 'Wrong number',
        ]);
    }

    public function test_admin_approve_credits_buyer(): void
    {
        $session = $this->billedCallSession(25);
        $return = CallReturn::create([
            'call_session_id' => $session->id,
            'buyer_id' => $this->buyer->id,
            'reason' => 'Bad lead',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->post(route('call-logic.calls.returns.approve', [$session->uuid, $return->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $session->refresh();
        $return->refresh();

        $this->assertSame('approved', $return->status);
        $this->assertNotNull($session->refunded_at);
        $this->assertEquals(50, (float) $this->buyer->fresh()->credit_balance);
        $this->assertNotNull($return->refund_transaction_id);
    }

    public function test_admin_reject_keeps_debit(): void
    {
        $session = $this->billedCallSession(25);
        $return = CallReturn::create([
            'call_session_id' => $session->id,
            'buyer_id' => $this->buyer->id,
            'reason' => 'Not valid',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->post(route('call-logic.calls.returns.reject', [$session->uuid, $return->id]))
            ->assertRedirect();

        $session->refresh();
        $return->refresh();

        $this->assertSame('rejected', $return->status);
        $this->assertNull($session->refunded_at);
        $this->assertEquals(25, (float) $this->buyer->fresh()->credit_balance);
    }

    public function test_duplicate_return_is_blocked(): void
    {
        $session = $this->billedCallSession();

        CallReturn::create([
            'call_session_id' => $session->id,
            'buyer_id' => $this->buyer->id,
            'reason' => 'First',
            'status' => 'pending',
        ]);

        $this->host()
            ->actingAs($this->buyerUser)
            ->post(route('portal.buyer.calls.return', $session->uuid), [
                'reason' => 'Second',
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_unbilled_call_cannot_be_returned(): void
    {
        $campaign = $this->createCallCampaign();
        $delivery = $this->createCallDelivery($campaign, 20);

        $session = CallSession::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'status' => CallStatus::Completed,
            'caller_number' => '+447700900123',
            'sold_to_buyer_id' => $this->buyer->id,
            'winning_delivery_id' => $delivery->id,
        ]);

        $this->host()
            ->actingAs($this->buyerUser)
            ->post(route('portal.buyer.calls.return', $session->uuid), [
                'reason' => 'Too early',
            ])
            ->assertSessionHasErrors('reason');
    }

    protected function billedCallSession(float $amount = 20): CallSession
    {
        $campaign = $this->createCallCampaign();
        $delivery = $this->createCallDelivery($campaign, $amount);

        $session = CallSession::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'status' => CallStatus::Completed,
            'caller_number' => '+447700900123',
            'sold_to_buyer_id' => $this->buyer->id,
            'winning_delivery_id' => $delivery->id,
            'revenue' => $amount,
            'billed_at' => now(),
            'billed_amount' => $amount,
        ]);

        BuyerTransaction::create([
            'buyer_id' => $this->buyer->id,
            'type' => 'debit',
            'amount' => -$amount,
            'balance_after' => 50 - $amount,
            'description' => 'Call purchase',
            'meta' => ['call_session_id' => $session->id, 'call_session_uuid' => $session->uuid],
        ]);

        $this->buyer->update(['credit_balance' => 50 - $amount]);

        return $session->fresh();
    }

    protected function createCallCampaign(): Campaign
    {
        return Campaign::create([
            'account_id' => $this->account->id,
            'type' => 'standard',
            'channel' => 'call',
            'name' => 'Call Return Campaign',
            'reference' => 'call-ret-'.uniqid(),
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'floor_price' => 10,
            'ping_timeout_ms' => 1500,
            'call_settings' => ['routing_mode' => 'waterfall', 'min_duration_seconds' => 30],
        ]);
    }

    protected function createCallDelivery(Campaign $campaign, float $amount): Delivery
    {
        return Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $this->buyer->id,
            'name' => 'Call buyer',
            'method' => DeliveryMethod::CallDirectTransfer,
            'trigger_type' => 'on_lead_arrival',
            'status' => 'active',
            'priority' => 1,
            'tier' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => $amount,
            'config' => ['destination_phone' => '+441234567890'],
        ]);
    }
}
