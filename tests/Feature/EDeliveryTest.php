<?php

namespace Tests\Feature;

use App\Models\BulkSmsCampaign;
use App\Models\Lead;
use App\Models\MessageSend;
use App\Models\Segment;
use App\Models\User;
use App\Services\Messaging\DeliverabilityReportService;
use App\Services\Messaging\MarketingSuppressionService;
use App\Services\Messaging\MessageSendService;
use App\Services\Messaging\SegmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
    }

    public function test_scheduled_bulk_campaign_is_processed_by_command(): void
    {
        $campaign = BulkSmsCampaign::create([
            'account_id' => $this->admin->resolveAccount()->id,
            'name' => 'Scheduled blast',
            'message' => 'Hello',
            'channel' => 'sms',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
        ]);

        $this->artisan('bulk:process-scheduled')->assertSuccessful();

        $campaign->refresh();
        $this->assertSame('queued', $campaign->status);
    }

    public function test_marketing_suppression_blocks_opt_out_email(): void
    {
        $account = $this->admin->resolveAccount();
        app(MarketingSuppressionService::class)->optOut($account->id, 'email', 'blocked@example.com');

        $this->assertTrue(
            app(MarketingSuppressionService::class)->isSuppressed($account->id, 'email', 'blocked@example.com')
        );
    }

    public function test_message_send_service_creates_audit_row(): void
    {
        $account = $this->admin->resolveAccount();

        $sent = app(MessageSendService::class)->send([
            'account_id' => $account->id,
            'channel' => 'sms',
            'recipient' => '+447700900123',
            'body' => 'Test message',
            'provider' => 'log',
            'track' => false,
        ]);

        $this->assertTrue($sent);
        $this->assertDatabaseHas('message_sends', [
            'account_id' => $account->id,
            'recipient' => '+447700900123',
            'channel' => 'sms',
        ]);
    }

    public function test_segment_service_tags_lead(): void
    {
        $lead = Lead::first();

        app(SegmentService::class)->tagLead($lead, 'engaged');

        $this->assertDatabaseHas('lead_tags', [
            'lead_id' => $lead->id,
            'tag' => 'engaged',
        ]);
    }

    public function test_deliverability_summary_returns_metrics(): void
    {
        $account = $this->admin->resolveAccount();

        MessageSend::create([
            'account_id' => $account->id,
            'channel' => 'email',
            'recipient' => 'a@example.com',
            'subject' => 'Hi',
            'body' => 'Body',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $summary = app(DeliverabilityReportService::class)->summary($account->id);

        $this->assertSame(1, $summary['total_sent']);
        $this->assertArrayHasKey('open_rate', $summary);
        $this->assertArrayHasKey('complaint_rate', $summary);
        $this->assertArrayHasKey('delivery_rate', $summary);
        $this->assertArrayHasKey('click_to_open_rate', $summary);
        $this->assertSame(30, $summary['period_days']);
    }

    public function test_e_delivery_hub_loads_when_routes_wired(): void
    {
        if (! Route::has('e-delivery.index')) {
            $this->markTestSkipped('E-Delivery routes not wired — Integration Lead should require routes/e-delivery.php');
        }

        $this->actingAs($this->admin)
            ->get(route('e-delivery.index'))
            ->assertOk();
    }

    public function test_open_tracking_when_routes_wired(): void
    {
        if (! Route::has('messaging.track.open')) {
            $this->markTestSkipped('E-Delivery public routes not wired');
        }

        $account = $this->admin->resolveAccount();
        $send = MessageSend::create([
            'account_id' => $account->id,
            'channel' => 'email',
            'recipient' => 'track@example.com',
            'subject' => 'Test',
            'body' => 'Hi',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->get(route('messaging.track.open', $send->token))->assertOk();

        $this->assertDatabaseHas('message_events', [
            'message_send_id' => $send->id,
            'type' => 'open',
        ]);
    }

    public function test_segment_store_when_routes_wired(): void
    {
        if (! Route::has('e-delivery.segments.store')) {
            $this->markTestSkipped('E-Delivery admin routes not wired');
        }

        $this->actingAs($this->admin)
            ->post(route('e-delivery.segments.store'), [
                'name' => 'Opened last 7 days',
                'rules' => ['opened_last_days' => 7, 'has_email' => true],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('segments', ['name' => 'Opened last 7 days']);
    }
}
