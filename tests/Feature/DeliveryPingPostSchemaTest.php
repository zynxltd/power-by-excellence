<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Enums\LeadStatus;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Services\Delivery\DeliveryExecutor;
use App\Services\Delivery\DeliveryPayloadBuilder;
use App\Services\Delivery\DeliveryResponseMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryPingPostSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_custom_ping_payload_without_legacy_floor_injection(): void
    {
        $builder = app(DeliveryPayloadBuilder::class);

        $payload = $builder->buildPingPayload([
            'ping_payload' => '{"auction":{"zip":"[zipcode]","min_bid":[floor]}}',
            'ping_include_floor' => false,
            'ping_include_bid_hint' => false,
        ], ['zipcode' => '90210'], 12.5);

        $this->assertSame([
            'auction' => [
                'zip' => '90210',
                'min_bid' => 12.5,
            ],
        ], $payload);
        $this->assertArrayNotHasKey('floor', $payload);
    }

    public function test_legacy_ping_payload_still_injects_top_level_floor_and_bid_hint(): void
    {
        $builder = app(DeliveryPayloadBuilder::class);

        $payload = $builder->buildPingPayload([
            'bid_hint' => 21,
        ], ['zipcode' => '90210'], 12.0);

        $this->assertSame(12.0, $payload['floor']);
        $this->assertSame(21, $payload['bid_hint']);
    }

    public function test_custom_ping_success_rules_and_price_field(): void
    {
        $matcher = app(DeliveryResponseMatcher::class);

        $accepted = $matcher->matchesPingSuccess([
            'ping_success_rules' => [
                ['field' => 'status.code', 'op' => 'eq', 'value' => 'accepted'],
            ],
            'ping_price_field' => 'bid.amount',
        ], [
            'status' => ['code' => 'accepted'],
            'bid' => ['amount' => 30],
        ], 15);

        $this->assertTrue($accepted);
    }

    public function test_custom_post_payload_uses_ping_response_tokens(): void
    {
        Http::fake([
            'https://buyer-custom.test/ping' => Http::response([
                'status' => ['code' => 'accepted'],
                'bid' => ['amount' => 28],
                'token' => 'abc123',
            ], 200),
            'https://buyer-custom.test/post' => Http::response(['result' => 'ok'], 200),
        ]);

        [$campaign, $delivery] = $this->pingDeliveryWithConfig([
            'ping_url' => 'https://buyer-custom.test/ping',
            'post_url' => 'https://buyer-custom.test/post',
            'ping_payload' => '{"lead":{"zip":"[zipcode]"},"auction":{"min_bid":[floor]}}',
            'ping_include_floor' => false,
            'ping_include_bid_hint' => false,
            'ping_success_rules' => [
                ['field' => 'status.code', 'op' => 'eq', 'value' => 'accepted'],
            ],
            'ping_price_field' => 'bid.amount',
            'post_payload' => '{"token":"{$ping.token}","email":"[email]","firstname":"[firstname]"}',
            'response_rules' => [
                ['match_by' => 'json_path', 'field' => 'result', 'value' => 'ok', 'label' => 'success'],
            ],
            'revenue_field' => 'bid.amount',
        ]);

        $lead = $this->lead($campaign);
        app(DeliveryExecutor::class)->execute($lead, $delivery, ['zipcode' => '90210']);

        $log = DeliveryLog::where('lead_id', $lead->id)->firstOrFail();

        $this->assertSame([
            'lead' => ['zip' => '90210'],
            'auction' => ['min_bid' => 12.5],
        ], $log->ping_request['body']);

        $this->assertSame([
            'token' => 'abc123',
            'email' => $lead->getField('email'),
            'firstname' => 'Schema',
        ], $log->post_request['body']);

        $this->assertSame('success', $log->status);
    }

    public function test_post_success_rule_can_match_json_path_instead_of_http_status_only(): void
    {
        Http::fake([
            'https://buyer-json.test/ping' => Http::response(['Success' => true, 'Cost' => 20], 200),
            'https://buyer-json.test/post' => Http::response(['Approved' => true], 422),
        ]);

        [, $delivery] = $this->pingDeliveryWithConfig([
            'ping_url' => 'https://buyer-json.test/ping',
            'post_url' => 'https://buyer-json.test/post',
            'response_rules' => [
                ['match_by' => 'json_path', 'field' => 'Approved', 'value' => true, 'label' => 'success'],
            ],
        ]);

        $lead = $this->lead(Campaign::find($delivery->campaign_id));
        app(DeliveryExecutor::class)->execute($lead, $delivery);

        $this->assertSame('success', DeliveryLog::where('lead_id', $lead->id)->value('status'));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{0: Campaign, 1: Delivery}
     */
    protected function pingDeliveryWithConfig(array $config): array
    {
        $campaign = Campaign::firstOrFail();
        $campaign->update(['floor_price' => 12.5]);
        $buyer = Buyer::where('account_id', $campaign->account_id)->firstOrFail();

        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Schema test delivery',
            'method' => DeliveryMethod::PingPost,
            'status' => 'active',
            'trigger_type' => 'on_lead_arrival',
            'priority' => 1,
            'revenue_type' => 'dynamic',
            'revenue_amount' => 10,
            'config' => array_merge([
                'revenue_field' => 'Cost',
            ], $config),
        ]);

        return [$campaign, $delivery];
    }

    protected function lead(Campaign $campaign): Lead
    {
        return Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => [
                'firstname' => 'Schema',
                'lastname' => 'Test',
                'email' => 'schema-test@example.com',
                'phone1' => '07700900999',
                'zipcode' => '90210',
            ],
            'received_at' => now(),
        ]);
    }
}
