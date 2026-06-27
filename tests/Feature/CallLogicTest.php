<?php

namespace Tests\Feature;

use App\Enums\CallStatus;
use App\Enums\DeliveryMethod;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\IvrFlow;
use App\Models\TrackingNumber;
use App\Models\User;
use App\Services\Api\ApiKeyService;
use App\Support\Products\CallLogicProduct;
use App\Support\Tenancy\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallLogicTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->account = $this->admin->account;
        CallLogicProduct::enable($this->account);
        $this->account->refresh();
    }

    public function test_call_logic_settings_page_loads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('call-logic.settings.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/CallLogic/Settings')->where('enabled', true));
    }

    public function test_tracking_number_can_be_provisioned(): void
    {
        $campaign = $this->createCallCampaign();

        $this->actingAs($this->admin)
            ->post(route('call-logic.tracking-numbers.store'), [
                'campaign_id' => $campaign->id,
                'friendly_name' => 'Main line',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tracking_numbers', [
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'friendly_name' => 'Main line',
            'status' => 'active',
        ]);
    }

    public function test_inbound_webhook_creates_call_session(): void
    {
        $campaign = $this->createCallCampaign();
        $tracking = TrackingNumber::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'phone_number' => '+442071234567',
            'provider' => 'log',
            'status' => 'active',
        ]);

        $this->post('/webhooks/twilio/voice/'.$this->account->slug, [
            'To' => $tracking->phone_number,
            'From' => '+447700900123',
            'CallSid' => 'CA_test_123',
        ])->assertOk()->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

        $this->assertDatabaseHas('call_sessions', [
            'account_id' => $this->account->id,
            'caller_number' => '+447700900123',
            'provider_call_sid' => 'CA_test_123',
        ]);
    }

    public function test_call_ping_post_routes_to_buyer(): void
    {
        $campaign = $this->createCallCampaign(['call_settings' => ['routing_mode' => 'waterfall']]);
        $buyer = Buyer::where('account_id', $this->account->id)->first();

        Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Call buyer tier 1',
            'method' => DeliveryMethod::CallPingPost,
            'trigger_type' => 'on_lead_arrival',
            'status' => 'active',
            'priority' => 1,
            'tier' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => 25,
            'config' => [
                'ping_url' => url('/api/v1/mock/call-buyers/1/ping'),
                'destination_phone' => '+441234567890',
            ],
        ]);

        $session = CallSession::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'status' => CallStatus::Routing,
            'caller_number' => '+447700900123',
            'min_duration_seconds' => 30,
        ]);

        AccountContext::set($this->account);
        $result = app(\App\Services\Calls\CallRouter::class)->route($session->fresh());

        $this->assertTrue($result->success);
        $session->refresh();
        $this->assertSame($buyer->id, $session->sold_to_buyer_id);
        $this->assertNotNull($session->revenue);
    }

    public function test_disposition_api_updates_call(): void
    {
        $session = CallSession::create([
            'account_id' => $this->account->id,
            'status' => CallStatus::Connected,
            'caller_number' => '+447700900123',
            'min_duration_seconds' => 10,
        ]);

        $token = app(ApiKeyService::class)->create([
            'account_id' => $this->account->id,
            'name' => 'Call API',
            'type' => 'administrator',
            'permissions' => ['leads.read'],
        ])['token'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/calls/{$session->uuid}/disposition", [
                'disposition' => 'connected',
                'duration_seconds' => 120,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $session->refresh();
        $this->assertSame('completed', $session->status->value);
        $this->assertSame(120, $session->duration_seconds);
        $this->assertSame(120, $session->billable_seconds);
    }

    public function test_dni_resolve_returns_tracking_number(): void
    {
        TrackingNumber::create([
            'account_id' => $this->account->id,
            'phone_number' => '+442079999999',
            'status' => 'active',
        ]);

        $this->getJson('/api/v1/dni/resolve?account_slug='.$this->account->slug)
            ->assertOk()
            ->assertJsonPath('phone_number', '+442079999999');
    }

    public function test_ivr_flow_crud(): void
    {
        $campaign = $this->createCallCampaign();

        $this->actingAs($this->admin)
            ->post(route('call-logic.ivr.store'), [
                'name' => 'Welcome flow',
                'campaign_id' => $campaign->id,
                'entry_node' => 'start',
                'nodes' => ['start' => ['type' => 'route']],
                'is_active' => true,
            ])
            ->assertRedirect(route('call-logic.ivr.index'));

        $this->assertDatabaseHas('ivr_flows', ['name' => 'Welcome flow', 'campaign_id' => $campaign->id]);
    }

    public function test_call_analytics_summary(): void
    {
        CallSession::create([
            'account_id' => $this->account->id,
            'status' => CallStatus::Completed,
            'caller_number' => '+441',
            'duration_seconds' => 90,
            'revenue' => 20,
            'sold_to_buyer_id' => Buyer::where('account_id', $this->account->id)->value('id'),
        ]);

        AccountContext::set($this->account);
        $summary = app(\App\Services\Calls\CallAnalyticsService::class)->summary($this->account->id);

        $this->assertSame(1, $summary['total_calls']);
        $this->assertSame(20.0, $summary['revenue']);
    }

    public function test_hybrid_fallback_creates_lead(): void
    {
        $leadCampaign = Campaign::where('account_id', $this->account->id)->first();
        $callCampaign = $this->createCallCampaign([
            'channel' => 'hybrid',
            'call_settings' => ['fallback_campaign_id' => $leadCampaign->id],
        ]);

        $session = CallSession::create([
            'account_id' => $this->account->id,
            'campaign_id' => $callCampaign->id,
            'status' => CallStatus::Routing,
            'caller_number' => '+447700900456',
        ]);

        AccountContext::set($this->account);
        $result = app(\App\Services\Calls\CallRouter::class)->route($session->fresh());

        $this->assertNotNull($result->fallbackLead);
        $this->assertDatabaseHas('leads', [
            'campaign_id' => $leadCampaign->id,
            'account_id' => $this->account->id,
        ]);
    }

    public function test_call_logic_routes_require_product_enabled(): void
    {
        CallLogicProduct::disable($this->account->fresh());

        $this->actingAs($this->admin)
            ->get(route('call-logic.calls.index'))
            ->assertRedirect(route('settings.edit'));
    }

    protected function createCallCampaign(array $overrides = []): Campaign
    {
        return Campaign::create(array_merge([
            'account_id' => $this->account->id,
            'type' => 'standard',
            'channel' => 'call',
            'name' => 'Call Test Campaign',
            'reference' => 'call-test-'.uniqid(),
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'floor_price' => 10,
            'ping_timeout_ms' => 1500,
            'call_settings' => ['routing_mode' => 'waterfall', 'min_duration_seconds' => 30],
        ], $overrides));
    }
}
