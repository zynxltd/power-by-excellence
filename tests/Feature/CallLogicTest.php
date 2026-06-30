<?php

namespace Tests\Feature;

use App\Enums\CallStatus;
use App\Enums\DeliveryMethod;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\CallRecording;
use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\IvrFlow;
use App\Models\TrackingNumber;
use App\Models\User;
use App\Services\Api\ApiKeyService;
use App\Services\Telephony\TelephonyWebhookUrls;
use App\Services\Telephony\TwilioVoiceGateway;
use App\Support\CallLogic\CallLogicRouteRegistrar;
use App\Support\Products\CallLogicProduct;
use App\Support\Tenancy\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CallLogicTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

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
            'webhook_status' => 'configured',
        ]);
    }

    public function test_twilio_search_returns_available_numbers(): void
    {
        $this->mockTwilioGateway();

        $this->actingAs($this->admin)
            ->post(route('call-logic.tracking-numbers.search'), ['area_code' => '020', 'country' => 'GB'])
            ->assertRedirect()
            ->assertSessionHas('number_search_results', fn ($results) => count($results) >= 1);
    }

    public function test_twilio_purchase_creates_number_with_webhooks(): void
    {
        $this->mockTwilioGateway(purchase: true);
        config(['telephony.provider' => 'twilio']);
        $campaign = $this->createCallCampaign();

        $this->actingAs($this->admin)
            ->post(route('call-logic.tracking-numbers.purchase'), [
                'phone_number' => '+442071111111',
                'campaign_id' => $campaign->id,
                'friendly_name' => 'Purchased line',
            ])
            ->assertRedirect(route('call-logic.tracking-numbers.index'));

        $webhooks = TelephonyWebhookUrls::forAccount($this->account);
        $this->assertDatabaseHas('tracking_numbers', [
            'phone_number' => '+442071111111',
            'provider' => 'twilio',
            'provider_sid' => 'PNMOCK123',
            'webhook_status' => 'configured',
        ]);
        $number = TrackingNumber::where('phone_number', '+442071111111')->first();
        $this->assertSame($webhooks['voice_url'], $number->metadata['webhooks']['voice_url'] ?? null);
    }

    public function test_twilio_release_deactivates_tracking_number(): void
    {
        $this->mockTwilioGateway(release: true);
        $campaign = $this->createCallCampaign();
        $number = TrackingNumber::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'phone_number' => '+442071111111',
            'provider' => 'twilio',
            'provider_sid' => 'PNMOCK123',
            'webhook_status' => 'configured',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('call-logic.tracking-numbers.destroy', $number))
            ->assertRedirect();

        $this->assertSame('released', $number->fresh()->status);
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
        Http::fake([
            '*/api/v1/mock/call-buyers/1/ping' => Http::response([
                'Success' => true,
                'Cost' => 25,
                'PingID' => 'call_ping_test',
            ]),
        ]);

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
                'nodes' => [
                    'start' => ['type' => 'say', 'message' => 'Welcome', 'next' => 'route'],
                    'route' => ['type' => 'route'],
                ],
                'is_active' => true,
            ])
            ->assertRedirect(route('call-logic.ivr.index'));

        $this->assertDatabaseHas('ivr_flows', ['name' => 'Welcome flow', 'campaign_id' => $campaign->id]);
    }

    public function test_ivr_visual_builder_saves_three_step_graph(): void
    {
        $campaign = $this->createCallCampaign();

        $nodes = [
            'welcome' => ['type' => 'say', 'message' => 'Hello', 'next' => 'menu'],
            'menu' => [
                'type' => 'gather',
                'prompt' => 'Press 1 to continue',
                'store_as' => 'choice',
                'branches' => ['1' => 'handoff'],
            ],
            'handoff' => ['type' => 'redirect', 'next' => 'route'],
            'route' => ['type' => 'route'],
        ];

        $this->actingAs($this->admin)
            ->post(route('call-logic.ivr.store'), [
                'name' => 'Three step flow',
                'campaign_id' => $campaign->id,
                'entry_node' => 'welcome',
                'nodes' => $nodes,
                'is_active' => true,
            ])
            ->assertRedirect(route('call-logic.ivr.index'));

        $flow = IvrFlow::where('name', 'Three step flow')->first();
        $this->assertNotNull($flow);
        $this->assertSame('welcome', $flow->entry_node);
        $this->assertArrayHasKey('handoff', $flow->nodes);
        $this->assertSame('redirect', $flow->nodes['handoff']['type']);
    }

    public function test_inbound_ivr_follows_gather_redirect_path(): void
    {
        $campaign = $this->createCallCampaign();
        IvrFlow::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'name' => 'Redirect path',
            'entry_node' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'say', 'message' => 'Hello', 'next' => 'menu'],
                'menu' => [
                    'type' => 'gather',
                    'prompt' => 'Press 1 for sales',
                    'store_as' => 'choice',
                    'branches' => ['1' => 'handoff'],
                ],
                'handoff' => ['type' => 'redirect', 'next' => 'route'],
                'route' => ['type' => 'route'],
            ],
            'is_active' => true,
        ]);

        $tracking = TrackingNumber::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'phone_number' => '+4420799888777',
            'provider' => 'log',
            'provider_sid' => 'LOGIVR1',
            'status' => 'active',
            'webhook_status' => 'configured',
        ]);

        $inbound = $this->post('/webhooks/twilio/voice/'.$this->account->slug, [
            'CallSid' => 'CA_ivr_inbound',
            'From' => '+447700900111',
            'To' => $tracking->phone_number,
        ]);

        $inbound->assertOk()->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $inbound->assertSee('<Gather', false);
        $inbound->assertSee('Press 1 for sales', false);

        $session = CallSession::where('provider_call_sid', 'CA_ivr_inbound')->first();
        $this->assertNotNull($session);
        $this->assertSame('menu', $session->metadata['ivr_current_node'] ?? null);

        $gather = $this->post('/webhooks/twilio/voice/'.$this->account->slug.'/gather?session='.$session->uuid, [
            'Digits' => '1',
        ]);

        $gather->assertOk()->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $session->refresh();
        $this->assertSame('1', $session->ivr_data['choice'] ?? null);
        $gather->assertSee('Thank you for calling', false);
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

    public function test_tracking_number_can_be_released(): void
    {
        $campaign = $this->createCallCampaign();
        $number = TrackingNumber::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'phone_number' => '+442071111111',
            'provider' => 'log',
            'provider_sid' => 'LOGTEST123',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('call-logic.tracking-numbers.destroy', $number))
            ->assertRedirect();

        $this->assertSame('released', $number->fresh()->status);
    }

    public function test_ivr_flow_can_be_updated(): void
    {
        $campaign = $this->createCallCampaign();
        $flow = IvrFlow::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'name' => 'Original',
            'entry_node' => 'start',
            'nodes' => ['start' => ['type' => 'route']],
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('call-logic.ivr.update', $flow), [
                'name' => 'Updated flow',
                'campaign_id' => $campaign->id,
                'entry_node' => 'start',
                'nodes' => [
                    'start' => ['type' => 'gather', 'prompt' => 'Press 1'],
                    'route' => ['type' => 'route'],
                ],
                'is_active' => true,
            ])
            ->assertRedirect(route('call-logic.ivr.index'));

        $this->assertDatabaseHas('ivr_flows', ['id' => $flow->id, 'name' => 'Updated flow']);
    }

    public function test_admin_can_list_and_show_call_sessions(): void
    {
        $session = CallSession::create([
            'account_id' => $this->account->id,
            'status' => CallStatus::Completed,
            'caller_number' => '+447700900999',
            'duration_seconds' => 45,
        ]);

        $this->actingAs($this->admin)
            ->get(route('call-logic.calls.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/CallLogic/Calls/Index'));

        $this->actingAs($this->admin)
            ->get(route('call-logic.calls.show', $session->uuid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/CallLogic/Calls/Show')
                ->where('call.uuid', $session->uuid));
    }

    public function test_gather_webhook_advances_ivr_with_digits(): void
    {
        $campaign = $this->createCallCampaign();
        $flow = IvrFlow::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'name' => 'Gather test',
            'entry_node' => 'menu',
            'nodes' => [
                'menu' => [
                    'type' => 'gather',
                    'prompt' => 'Press 1 for sales',
                    'store_as' => 'choice',
                    'branches' => ['1' => 'route_node'],
                ],
                'route_node' => ['type' => 'route'],
            ],
            'is_active' => true,
        ]);

        $session = CallSession::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'ivr_flow_id' => $flow->id,
            'status' => CallStatus::InIvr,
            'caller_number' => '+447700900123',
            'metadata' => ['ivr_current_node' => 'menu'],
        ]);

        $this->post('/webhooks/twilio/voice/'.$this->account->slug.'/gather?session='.$session->uuid, [
            'Digits' => '1',
        ])->assertOk()->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

        $session->refresh();
        $this->assertSame('1', $session->ivr_data['choice'] ?? null);
    }

    public function test_status_webhook_records_disposition(): void
    {
        $session = CallSession::create([
            'account_id' => $this->account->id,
            'status' => CallStatus::Connected,
            'caller_number' => '+447700900123',
            'provider_call_sid' => 'CA_status_test',
            'min_duration_seconds' => 5,
        ]);

        $this->post('/webhooks/twilio/voice/'.$this->account->slug.'/status', [
            'CallSid' => 'CA_status_test',
            'CallStatus' => 'completed',
            'CallDuration' => 90,
        ])->assertNoContent();

        $session->refresh();
        $this->assertSame('completed', $session->status->value);
        $this->assertSame(90, $session->duration_seconds);
    }

    public function test_recording_webhook_downloads_and_stores_recording(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://api.twilio.com/recording.mp3' => Http::response('fake-audio-bytes', 200, ['Content-Type' => 'audio/mpeg']),
        ]);

        $session = CallSession::create([
            'account_id' => $this->account->id,
            'status' => CallStatus::Completed,
            'caller_number' => '+447700900123',
            'provider_call_sid' => 'CA_rec_test',
        ]);

        $this->post('/webhooks/twilio/voice/'.$this->account->slug.'/recording', [
            'CallSid' => 'CA_rec_test',
            'RecordingSid' => 'RE123',
            'RecordingUrl' => 'https://api.twilio.com/recording.mp3',
            'RecordingDuration' => 60,
            'RecordingStatus' => 'completed',
        ])->assertNoContent();

        $recording = CallRecording::where('provider_recording_sid', 'RE123')->first();
        $this->assertNotNull($recording);
        $this->assertNotNull($recording->storage_path);
        $this->assertSame('stored', $recording->status);
        $this->assertNotNull($recording->retention_expires_at);
        Storage::disk('local')->assertExists($recording->storage_path);
    }

    public function test_recording_play_route_streams_stored_audio(): void
    {
        Storage::fake('local');
        $path = 'call-recordings/'.$this->account->id.'/1/RE_play.mp3';
        Storage::disk('local')->put($path, 'audio-bytes');

        $session = CallSession::create([
            'account_id' => $this->account->id,
            'status' => CallStatus::Completed,
            'caller_number' => '+447700900123',
            'provider_call_sid' => 'CA_play_test',
        ]);

        $recording = CallRecording::create([
            'call_session_id' => $session->id,
            'provider_recording_sid' => 'RE_play',
            'storage_path' => $path,
            'duration_seconds' => 45,
            'status' => 'stored',
            'retention_expires_at' => now()->addDays(30),
        ]);

        $this->assertTrue($recording->hasPlayback());

        $this->actingAs($this->admin)
            ->get(route('call-logic.recordings.play', $recording))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg');
    }

    public function test_reports_page_loads_analytics(): void
    {
        CallSession::create([
            'account_id' => $this->account->id,
            'status' => CallStatus::Completed,
            'caller_number' => '+441',
            'duration_seconds' => 60,
            'revenue' => 15,
            'sold_to_buyer_id' => Buyer::where('account_id', $this->account->id)->value('id'),
        ]);

        $this->actingAs($this->admin)
            ->get(route('call-logic.reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/CallLogic/Reports/Index')
                ->has('summary')
                ->has('byCampaign')
                ->has('trafficFlow'));
    }

    public function test_buyer_portal_lists_sold_calls(): void
    {
        $buyer = Buyer::where('account_id', $this->account->id)->first();
        $buyerUser = User::where('buyer_id', $buyer->id)->first();

        if (! $buyerUser) {
            $this->markTestSkipped('No buyer portal user in seeder.');
        }

        $session = CallSession::create([
            'account_id' => $this->account->id,
            'status' => CallStatus::Completed,
            'caller_number' => '+447700900555',
            'sold_to_buyer_id' => $buyer->id,
            'duration_seconds' => 120,
        ]);

        $this->actingAs($buyerUser)
            ->get(route('portal.buyer.calls'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portal/Buyer/Calls'));

        $this->actingAs($buyerUser)
            ->get(route('portal.buyer.calls.show', $session->uuid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Buyer/CallShow')
                ->where('call.uuid', $session->uuid));
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

    protected function mockTwilioGateway(bool $purchase = false, bool $release = false): TwilioVoiceGateway
    {
        config(['telephony.provider' => 'twilio']);
        $mock = \Mockery::mock(TwilioVoiceGateway::class);
        $mock->shouldReceive('provider')->andReturn('twilio');
        $mock->shouldReceive('searchAvailableNumbers')->andReturn([[
            'sid' => '+442071111111', 'phone_number' => '+442071111111', 'friendly_name' => 'London', 'locality' => 'London',
        ]]);
        if ($purchase) {
            $mock->shouldReceive('purchaseNumber')->once()->with('+442071111111', \Mockery::on(
                fn (array $w) => str_contains($w['voice_url'] ?? '', $this->account->slug)
            ))->andReturn(['sid' => 'PNMOCK123', 'phone_number' => '+442071111111', 'webhook_status' => 'configured']);
        }
        if ($release) {
            $mock->shouldReceive('releaseNumber')->once()->with('PNMOCK123');
        }
        $this->instance(TwilioVoiceGateway::class, $mock);

        return $mock;
    }
}
