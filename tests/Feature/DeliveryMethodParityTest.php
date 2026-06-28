<?php

namespace Tests\Feature;

use App\Enums\CallStatus;
use App\Enums\DeliveryMethod;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Lead;
use App\Models\User;
use App\Services\Delivery\DeliveryExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryMethodParityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

    protected Campaign $campaign;

    protected Buyer $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->account = $this->admin->account;
        $this->campaign = Campaign::where('account_id', $this->account->id)->first();
        $this->buyer = Buyer::where('account_id', $this->account->id)->first();
    }

    protected function makeLead(array $fieldOverrides = []): Lead
    {
        return Lead::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'accepted',
            'field_data' => array_merge([
                'firstname' => 'Alex',
                'email' => 'alex@example.com',
                'phone1' => '+447700900123',
                'zipcode' => 'SW1A 1AA',
            ], $fieldOverrides),
        ]);
    }

    protected function baseDeliveryPayload(DeliveryMethod $method, array $config = []): array
    {
        return [
            'campaign_id' => $this->campaign->id,
            'buyer_id' => $this->buyer->id,
            'name' => 'Parity '.$method->value,
            'method' => $method->value,
            'trigger_type' => 'on_lead_arrival',
            'status' => 'active',
            'priority' => 10,
            'weight' => 100,
            'tier' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => 20,
            'config' => $config,
        ];
    }

    public function test_admin_can_create_ping_only_delivery(): void
    {
        $this->actingAs($this->admin)
            ->post(route('deliveries.store'), $this->baseDeliveryPayload(DeliveryMethod::PingOnly, [
                'ping_url' => 'https://buyer.test/ping',
            ]))
            ->assertRedirect(route('deliveries.index'));

        $this->assertDatabaseHas('deliveries', [
            'name' => 'Parity ping_only',
            'method' => 'ping_only',
        ]);
    }

    public function test_ping_only_executor_succeeds_without_post(): void
    {
        Http::fake([
            'buyer.test/ping' => Http::response(['Success' => true, 'Cost' => 18]),
        ]);

        $delivery = Delivery::create(array_merge($this->baseDeliveryPayload(DeliveryMethod::PingOnly, [
            'ping_url' => 'https://buyer.test/ping',
            'ping_success_rules' => [['field' => 'Success', 'op' => 'eq', 'value' => true]],
        ]), ['name' => 'Ping only test']));

        $lead = $this->makeLead();
        $result = app(DeliveryExecutor::class)->execute($lead, $delivery);

        $this->assertTrue($result->success);
        $this->assertSame(20.0, $result->revenue);
        Http::assertSentCount(1);
    }

    public function test_two_step_auth_extracts_token_and_posts(): void
    {
        Http::fake([
            'buyer.test/ping' => Http::response(['Success' => true, 'Token' => 'secret-token', 'Cost' => 22]),
            'buyer.test/auth' => Http::response(['Approved' => true], 200),
        ]);

        $delivery = Delivery::create(array_merge($this->baseDeliveryPayload(DeliveryMethod::TwoStepAuth, [
            'ping_url' => 'https://buyer.test/ping',
            'post_url' => 'https://buyer.test/auth',
            'auth_token_field' => 'Token',
            'auth_header' => 'Authorization',
            'auth_header_prefix' => 'Bearer ',
            'ping_success_rules' => [['field' => 'Success', 'op' => 'eq', 'value' => true]],
            'response_rules' => [['match_by' => 'http_status', 'value' => '200', 'label' => 'success']],
        ]), ['name' => 'Two step auth']));

        $lead = $this->makeLead();
        $result = app(DeliveryExecutor::class)->execute($lead, $delivery);

        $this->assertTrue($result->success);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://buyer.test/auth'
                && $request->header('Authorization')[0] === 'Bearer secret-token';
        });
    }

    public function test_campaign_transfer_moves_lead_to_target_campaign(): void
    {
        $target = Campaign::where('account_id', $this->account->id)->skip(1)->first()
            ?? Campaign::factory()->create(['account_id' => $this->account->id, 'name' => 'Transfer target']);

        $delivery = Delivery::create(array_merge($this->baseDeliveryPayload(DeliveryMethod::CampaignTransfer, [
            'campaign_id' => $target->id,
        ]), ['name' => 'Transfer delivery']));

        $lead = $this->makeLead();
        $result = app(DeliveryExecutor::class)->execute($lead, $delivery);

        $this->assertTrue($result->success);
        $lead->refresh();
        $this->assertSame($target->id, $lead->campaign_id);
    }

    public function test_call_ping_post_http_handoff_when_no_session(): void
    {
        Http::fake([
            'buyer.test/call/ping' => Http::response(['Success' => true, 'Cost' => 30]),
        ]);

        $delivery = Delivery::create(array_merge($this->baseDeliveryPayload(DeliveryMethod::CallPingPost, [
            'ping_url' => 'https://buyer.test/call/ping',
            'destination_phone' => '+441234567890',
            'ping_success_rules' => [['field' => 'Success', 'op' => 'eq', 'value' => true]],
        ]), ['name' => 'Call ping post']));

        $lead = $this->makeLead();
        $result = app(DeliveryExecutor::class)->execute($lead, $delivery);

        $this->assertTrue($result->success);
        $this->assertSame(20.0, $result->revenue);
    }

    public function test_call_direct_transfer_assigns_session_when_present(): void
    {
        $session = CallSession::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'status' => CallStatus::Routing,
            'caller_number' => '+447700900123',
        ]);

        $delivery = Delivery::create(array_merge($this->baseDeliveryPayload(DeliveryMethod::CallDirectTransfer, [
            'destination_phone' => '+449876543210',
        ]), ['name' => 'Call direct transfer']));

        $lead = $this->makeLead(['call_session_uuid' => $session->uuid]);
        $result = app(DeliveryExecutor::class)->execute($lead, $delivery);

        $this->assertTrue($result->success);
        $session->refresh();
        $this->assertSame($this->buyer->id, $session->sold_to_buyer_id);
        $this->assertSame('+449876543210', $session->metadata['transfer_number'] ?? null);
    }

    public function test_delivery_store_validation_requires_method_specific_config(): void
    {
        $this->actingAs($this->admin)
            ->post(route('deliveries.store'), $this->baseDeliveryPayload(DeliveryMethod::PingOnly, []))
            ->assertSessionHasErrors('config.ping_url');

        $this->actingAs($this->admin)
            ->post(route('deliveries.store'), $this->baseDeliveryPayload(DeliveryMethod::CallDirectTransfer, []))
            ->assertSessionHasErrors('config.destination_phone');
    }
}
