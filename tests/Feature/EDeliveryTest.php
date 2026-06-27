<?php

namespace Tests\Feature;

use App\Models\BulkSmsCampaign;
use App\Models\Lead;
use App\Models\MessageSend;
use App\Models\MessageTemplate;
use App\Models\Segment;
use App\Models\SendingProfile;
use App\Models\User;
use App\Services\Messaging\DeliverabilityReportService;
use App\Services\Messaging\MarketingSuppressionService;
use App\Services\Messaging\MessageSendService;
use App\Services\Messaging\SegmentService;
use App\Services\Messaging\ThrottleGovernor;
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
        $this->registerEDeliveryRoutes();
    }

    protected function registerEDeliveryRoutes(): void
    {
        if (! function_exists('registerEDeliveryAdminRoutes')) {
            require base_path('routes/e-delivery.php');
        } else {
            Route::get('/messaging/open/{token}', [\App\Http\Controllers\MessageTrackingController::class, 'open'])->name('messaging.track.open');
            Route::get('/messaging/click/{token}', [\App\Http\Controllers\MessageTrackingController::class, 'click'])->name('messaging.track.click');
        }

        \registerEDeliveryAdminRoutes();
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

    public function test_deliverability_summary_returns_complete_metrics(): void
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
        $this->assertArrayHasKey('by_channel', $summary);
        $this->assertSame(30, $summary['period_days']);
    }

    public function test_throttle_governor_status_reports_queue(): void
    {
        $account = $this->admin->resolveAccount();

        BulkSmsCampaign::create([
            'account_id' => $account->id,
            'name' => 'Queued send',
            'message' => 'Hi',
            'channel' => 'email',
            'status' => 'scheduled',
            'scheduled_at' => now()->addHour(),
            'throttle_per_minute' => 50,
        ]);

        $status = app(ThrottleGovernor::class)->status($account->id);

        $this->assertSame(1, $status['queued_campaigns']);
        $this->assertFalse($status['paused']);
        $this->assertSame(50, $status['active_rate_per_minute']);
        $this->assertArrayHasKey('chunk_delay_seconds', $status);
    }

    public function test_messaging_integration_saves_esp_credentials(): void
    {
        $account = $this->admin->resolveAccount();

        $this->actingAs($this->admin)
            ->put('/integrations/messaging', [
                'email_provider' => 'sendgrid',
                'sms_provider' => 'log',
                'from_name' => 'Acme Loans',
                'from_email' => 'noreply@acme.test',
                'reply_to' => 'support@acme.test',
                'providers' => [
                    'sendgrid' => ['key' => 'SG.test-key-12345'],
                ],
            ])
            ->assertRedirect();

        $account->refresh();
        $messaging = $account->settings['messaging'];

        $this->assertSame('sendgrid', $messaging['email_provider']);
        $this->assertSame('Acme Loans', $messaging['from_name']);
        $this->assertSame('SG.test-key-12345', $messaging['providers']['sendgrid']['key']);
    }

    public function test_e_delivery_hub_loads_with_throttle_data(): void
    {
        $this->actingAs($this->admin)
            ->get('/e-delivery')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/EDelivery/Index')
                ->has('summary')
                ->has('hourlyOpens')
                ->has('campaignStats')
                ->has('throttle')
            );
    }

    public function test_end_to_end_segment_template_profile_schedule_and_tracking(): void
    {
        $account = $this->admin->resolveAccount();

        $segment = Segment::create([
            'account_id' => $account->id,
            'name' => 'Email holders',
            'rules' => ['has_email' => true],
        ]);

        MessageTemplate::create([
            'account_id' => $account->id,
            'name' => 'Welcome back',
            'channel' => 'email',
            'subject' => 'Still interested?',
            'body' => 'Hello [firstname], we have an offer.',
            'html_body' => '<p>Hello [firstname]</p>',
        ]);

        $profile = SendingProfile::create([
            'account_id' => $account->id,
            'name' => 'Gmail profile',
            'provider' => 'sendgrid',
            'domain_match' => 'gmail.com',
            'from_email' => 'news@tenant.test',
            'is_default' => true,
        ]);

        $template = MessageTemplate::where('name', 'Welcome back')->first();

        $campaign = BulkSmsCampaign::create([
            'account_id' => $account->id,
            'segment_id' => $segment->id,
            'sending_profile_id' => $profile->id,
            'name' => 'Re-engagement blast',
            'channel' => 'email',
            'subject' => $template->subject,
            'message' => $template->body,
            'html_body' => $template->html_body,
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
            'throttle_per_minute' => 60,
        ]);

        $this->artisan('bulk:process-scheduled')->assertSuccessful();
        $campaign->refresh();
        $this->assertSame('queued', $campaign->status);

        $send = MessageSend::create([
            'account_id' => $account->id,
            'bulk_sms_campaign_id' => $campaign->id,
            'channel' => 'email',
            'recipient' => 'reader@example.com',
            'subject' => 'Still interested?',
            'body' => 'Hello Alex',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->get('/messaging/open/'.$send->token)->assertOk();
        $this->assertDatabaseHas('message_events', [
            'message_send_id' => $send->id,
            'type' => 'open',
        ]);

        $targetUrl = base64_encode('https://example.com/offer');
        $this->get('/messaging/click/'.$send->token.'?url='.$targetUrl)
            ->assertRedirect('https://example.com/offer');

        $this->assertDatabaseHas('message_events', [
            'message_send_id' => $send->id,
            'type' => 'click',
        ]);

        $summary = app(DeliverabilityReportService::class)->summary($account->id);
        $this->assertGreaterThanOrEqual(1, $summary['opens']);
        $this->assertGreaterThanOrEqual(1, $summary['clicks']);

        $stats = app(DeliverabilityReportService::class)->campaignStats($account->id);
        $this->assertTrue($stats->contains(fn (array $row) => $row['bulk_sms_campaign_id'] === $campaign->id));
    }
}
