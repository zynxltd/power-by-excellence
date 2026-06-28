<?php

namespace Tests\Feature;

use App\Models\AutomationSequence;
use App\Models\AutomationSequenceEnrollment;
use App\Models\AutomationSequenceStep;
use App\Models\BulkSmsCampaign;
use App\Models\Lead;
use App\Models\MessageSend;
use App\Models\MessageTemplate;
use App\Models\Segment;
use App\Models\SendingProfile;
use App\Models\User;
use App\Services\Automation\AutomationSequenceService;
use App\Services\Messaging\AbTestService;
use App\Services\Messaging\BulkCampaignSender;
use App\Services\Messaging\DeliverabilityReportService;
use App\Services\Messaging\MarketingSuppressionService;
use App\Services\Messaging\MessageSendService;
use App\Services\Messaging\SegmentService;
use App\Services\Messaging\TemplateRenderService;
use App\Services\Messaging\ThrottleGovernor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
        require_once base_path('routes/e-delivery.php');
        registerEDeliveryJourneyRoutes();

        if (! Route::has('e-delivery.templates.preview')) {
            Route::post('e-delivery/templates/preview', [\App\Http\Controllers\Admin\EDeliveryController::class, 'previewTemplate'])
                ->name('e-delivery.templates.preview');
        }

        if (! Route::has('e-delivery.templates.update')) {
            Route::put('e-delivery/templates/{template}', [\App\Http\Controllers\Admin\EDeliveryController::class, 'updateTemplate'])
                ->name('e-delivery.templates.update');
        }

        if (! Route::has('e-delivery.throttle.pause')) {
            Route::post('e-delivery/throttle/pause', [\App\Http\Controllers\Admin\EDeliveryController::class, 'pauseSending'])
                ->name('e-delivery.throttle.pause');
        }

        if (! Route::has('e-delivery.throttle.resume')) {
            Route::post('e-delivery/throttle/resume', [\App\Http\Controllers\Admin\EDeliveryController::class, 'resumeSending'])
                ->name('e-delivery.throttle.resume');
        }
    }

    /**
     * @return array{segment: Segment, leads: \Illuminate\Support\Collection<int, Lead>}
     */
    protected function bulkTestSegment(int $leadCount = 1): array
    {
        $account = $this->admin->resolveAccount();
        $tag = 'e12-bulk-'.uniqid();

        $segment = Segment::create([
            'account_id' => $account->id,
            'name' => 'E12 bulk test',
            'rules' => ['tags' => [$tag]],
        ]);

        $leads = Lead::query()
            ->where('account_id', $account->id)
            ->limit($leadCount)
            ->get();

        foreach ($leads as $index => $lead) {
            $lead->update([
                'field_data' => array_merge($lead->field_data ?? [], [
                    'firstname' => 'Bulk',
                    'lastname' => "Lead{$index}",
                    'email' => "bulk{$index}@e12.test",
                    'phone1' => '+4477009001'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                ]),
            ]);
            app(SegmentService::class)->tagLead($lead->fresh(), $tag);
        }

        return ['segment' => $segment, 'leads' => $leads->fresh()];
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
                ->has('summary7d')
                ->has('summary30d')
                ->has('suppressionCount')
                ->has('deliverabilityAlerts')
                ->has('hourlyOpens')
                ->has('campaignStats')
                ->has('throttle')
                ->where('summary7d.period_days', 7)
                ->where('summary30d.period_days', 30)
                ->has('summary7d.bounce_rate')
                ->has('summary30d.complaint_rate')
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

    public function test_template_render_service_merges_tags_with_aliases(): void
    {
        $rendered = app(TemplateRenderService::class)->renderParts([
            'subject' => 'Hello {{first_name}}',
            'body' => 'Dear {{last_name}}, welcome.',
            'html_body' => '<p>Hi {{first_name}} {{last_name}}</p>',
        ], null, ['first_name' => 'Jamie', 'last_name' => 'Lee']);

        $this->assertSame('Hello Jamie', $rendered['subject']);
        $this->assertSame('Dear Lee, welcome.', $rendered['body']);
        $this->assertSame('<p>Hi Jamie Lee</p>', $rendered['html_body']);
    }

    public function test_message_send_merges_template_tags_from_lead(): void
    {
        $account = $this->admin->resolveAccount();
        $lead = Lead::first();
        $lead->update([
            'field_data' => array_merge($lead->field_data ?? [], [
                'firstname' => 'Jordan',
                'lastname' => 'Smith',
                'email' => 'jordan@example.com',
            ]),
        ]);

        $sent = app(MessageSendService::class)->send([
            'account_id' => $account->id,
            'lead_id' => $lead->id,
            'channel' => 'email',
            'recipient' => 'jordan@example.com',
            'subject' => 'Hi {{first_name}}',
            'body' => 'Hello {{first_name}} {{last_name}}',
            'html_body' => '<p>Hello {{first_name}}</p>',
            'provider' => 'log',
            'track' => false,
        ]);

        $this->assertTrue($sent);
        $this->assertDatabaseHas('message_sends', [
            'lead_id' => $lead->id,
            'subject' => 'Hi Jordan',
            'body' => 'Hello Jordan Smith',
        ]);
    }

    public function test_template_preview_endpoint_returns_rendered_html(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/e-delivery/templates/preview', [
                'subject' => 'Offer for {{first_name}}',
                'body' => 'Hi {{first_name}}',
                'html_body' => '<strong>{{first_name}}</strong>',
                'preview_data' => ['first_name' => 'Sam'],
            ])
            ->assertOk()
            ->assertJson([
                'subject' => 'Offer for Sam',
                'body' => 'Hi Sam',
                'html_body' => '<strong>Sam</strong>',
            ]);
    }

    public function test_segment_entry_enrolls_lead_and_sends_first_template_step(): void
    {
        $account = $this->admin->resolveAccount();
        $lead = Lead::first();
        $lead->update([
            'field_data' => array_merge($lead->field_data ?? [], [
                'firstname' => 'Taylor',
                'email' => 'taylor@example.com',
            ]),
        ]);

        $segment = Segment::create([
            'account_id' => $account->id,
            'name' => 'Engaged leads',
            'rules' => ['tags' => ['engaged']],
        ]);

        $template = MessageTemplate::create([
            'account_id' => $account->id,
            'name' => 'Drip welcome',
            'channel' => 'email',
            'subject' => 'Hi {{first_name}}',
            'body' => 'Hello {{first_name}}, welcome to the journey.',
        ]);

        $sequence = AutomationSequence::create([
            'account_id' => $account->id,
            'segment_id' => $segment->id,
            'name' => 'Engaged drip',
            'trigger_event' => 'on_segment_entry',
            'status' => 'active',
        ]);

        AutomationSequenceStep::create([
            'automation_sequence_id' => $sequence->id,
            'sort_order' => 0,
            'action' => 'send_template',
            'delay_minutes' => 0,
            'channel' => 'email',
            'config' => [
                'message_template_id' => $template->id,
                'to_field' => 'email',
            ],
        ]);

        app(SegmentService::class)->tagLead($lead, 'engaged');

        $this->assertDatabaseHas('automation_sequence_enrollments', [
            'lead_id' => $lead->id,
            'automation_sequence_id' => $sequence->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('message_sends', [
            'lead_id' => $lead->id,
            'subject' => 'Hi Taylor',
            'body' => 'Hello Taylor, welcome to the journey.',
        ]);
    }

    public function test_wait_step_schedules_next_sequence_send(): void
    {
        $account = $this->admin->resolveAccount();
        $lead = Lead::first();
        $lead->update([
            'field_data' => array_merge($lead->field_data ?? [], [
                'firstname' => 'Casey',
                'email' => 'casey@example.com',
            ]),
        ]);

        $template = MessageTemplate::create([
            'account_id' => $account->id,
            'name' => 'Follow-up',
            'channel' => 'email',
            'subject' => 'Checking in {{first_name}}',
            'body' => 'Hi {{first_name}}, following up.',
        ]);

        $sequence = AutomationSequence::create([
            'account_id' => $account->id,
            'name' => 'Wait then send',
            'trigger_event' => 'on_lead_received',
            'status' => 'active',
        ]);

        AutomationSequenceStep::create([
            'automation_sequence_id' => $sequence->id,
            'sort_order' => 0,
            'action' => 'wait',
            'delay_minutes' => 0,
            'channel' => 'email',
            'config' => [],
        ]);

        AutomationSequenceStep::create([
            'automation_sequence_id' => $sequence->id,
            'sort_order' => 1,
            'action' => 'send_template',
            'delay_minutes' => 60,
            'channel' => 'email',
            'config' => [
                'message_template_id' => $template->id,
                'to_field' => 'email',
            ],
        ]);

        app(AutomationSequenceService::class)->enrollLead($lead, $sequence->fresh('steps'));

        $enrollment = AutomationSequenceEnrollment::where('lead_id', $lead->id)->first();
        $this->assertSame(1, (int) $enrollment->current_step_order);
        $this->assertTrue($enrollment->next_run_at->isFuture());

        $this->assertDatabaseMissing('message_sends', [
            'lead_id' => $lead->id,
            'subject' => 'Checking in Casey',
        ]);

        $this->travel(61)->minutes();
        $this->artisan('automation:process-sequences')->assertSuccessful();

        $this->assertDatabaseHas('message_sends', [
            'lead_id' => $lead->id,
            'subject' => 'Checking in Casey',
        ]);

        $enrollment->refresh();
        $this->assertSame('completed', $enrollment->status);
    }

    public function test_drip_email_step_merges_template_from_lead_data(): void
    {
        $account = $this->admin->resolveAccount();
        $lead = Lead::first();
        $lead->update([
            'field_data' => array_merge($lead->field_data ?? [], [
                'firstname' => 'Jordan',
                'lastname' => 'Lee',
                'email' => 'jordan@example.com',
            ]),
        ]);

        $template = MessageTemplate::create([
            'account_id' => $account->id,
            'name' => 'Personalized drip',
            'channel' => 'email',
            'subject' => 'For {{first_name}} {{last_name}}',
            'body' => 'Dear {{first_name}}, we saved your spot.',
        ]);

        $sequence = AutomationSequence::create([
            'account_id' => $account->id,
            'name' => 'Lead received drip',
            'trigger_event' => 'on_lead_received',
            'status' => 'active',
        ]);

        AutomationSequenceStep::create([
            'automation_sequence_id' => $sequence->id,
            'sort_order' => 0,
            'action' => 'send_template',
            'delay_minutes' => 0,
            'channel' => 'email',
            'config' => [
                'message_template_id' => $template->id,
                'to_field' => 'email',
            ],
        ]);

        app(AutomationSequenceService::class)->dispatchForLead($lead, 'on_lead_received');

        $this->assertDatabaseHas('message_sends', [
            'lead_id' => $lead->id,
            'subject' => 'For Jordan Lee',
            'body' => 'Dear Jordan, we saved your spot.',
        ]);
    }

    public function test_sendgrid_bounce_webhook_creates_suppression_and_event(): void
    {
        $account = $this->admin->resolveAccount();

        $send = MessageSend::create([
            'account_id' => $account->id,
            'channel' => 'email',
            'recipient' => 'bounced@example.com',
            'subject' => 'Hello',
            'body' => 'Body',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->postJson('/webhooks/esp/sendgrid', [
            [
                'email' => 'bounced@example.com',
                'event' => 'bounce',
                'reason' => '550 mailbox unavailable',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('message_events', [
            'message_send_id' => $send->id,
            'type' => 'bounce',
        ]);

        $this->assertTrue(
            app(MarketingSuppressionService::class)->isSuppressed($account->id, 'email', 'bounced@example.com')
        );
    }

    public function test_ops_center_includes_multi_period_bounce_and_complaint_rates(): void
    {
        $account = $this->admin->resolveAccount();

        $send = MessageSend::create([
            'account_id' => $account->id,
            'channel' => 'email',
            'recipient' => 'ops@example.com',
            'subject' => 'Hi',
            'body' => 'Body',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        app(MessageSendService::class)->recordEvent($send, 'bounce');
        app(MessageSendService::class)->recordEvent($send, 'complaint');

        $ops = app(DeliverabilityReportService::class)->opsCenter($account->id, $account);

        $this->assertSame(7, $ops['summary_7d']['period_days']);
        $this->assertSame(30, $ops['summary_30d']['period_days']);
        $this->assertGreaterThanOrEqual(1, $ops['summary_7d']['bounces']);
        $this->assertGreaterThanOrEqual(1, $ops['summary_30d']['complaints']);
        $this->assertArrayHasKey('bounce_rate', $ops['summary_7d']);
        $this->assertArrayHasKey('complaint_rate', $ops['summary_30d']);
    }

    public function test_manual_throttle_pause_blocks_message_send_service(): void
    {
        $account = $this->admin->resolveAccount();
        app(ThrottleGovernor::class)->pauseSending($account->id);

        $sent = app(MessageSendService::class)->send([
            'account_id' => $account->id,
            'channel' => 'email',
            'recipient' => 'blocked@example.com',
            'subject' => 'Test',
            'body' => 'Should not send',
            'provider' => 'log',
            'track' => false,
        ]);

        $this->assertFalse($sent);
        $this->assertDatabaseMissing('message_sends', [
            'account_id' => $account->id,
            'recipient' => 'blocked@example.com',
        ]);
    }

    public function test_bulk_email_campaign_sends_email_only(): void
    {
        $account = $this->admin->resolveAccount();
        ['segment' => $segment, 'leads' => $leads] = $this->bulkTestSegment(1);
        $lead = $leads->first();

        $campaign = BulkSmsCampaign::create([
            'account_id' => $account->id,
            'segment_id' => $segment->id,
            'name' => 'Email-only blast',
            'channel' => 'email',
            'subject' => 'Hello email',
            'message' => 'Email body for bulk',
            'html_body' => '<p>Email HTML</p>',
            'provider' => 'log',
            'status' => 'draft',
        ]);

        app(BulkCampaignSender::class)->send($campaign);

        $campaign->refresh();
        $this->assertSame('completed', $campaign->status);
        $this->assertSame(1, $campaign->sent_count);

        $this->assertDatabaseHas('message_sends', [
            'bulk_sms_campaign_id' => $campaign->id,
            'lead_id' => $lead->id,
            'channel' => 'email',
            'recipient' => 'bulk0@e12.test',
        ]);
        $this->assertDatabaseMissing('message_sends', [
            'bulk_sms_campaign_id' => $campaign->id,
            'channel' => 'sms',
        ]);
    }

    public function test_bulk_sms_campaign_sends_sms_only(): void
    {
        $account = $this->admin->resolveAccount();
        ['segment' => $segment, 'leads' => $leads] = $this->bulkTestSegment(1);
        $lead = $leads->first();

        $campaign = BulkSmsCampaign::create([
            'account_id' => $account->id,
            'segment_id' => $segment->id,
            'name' => 'SMS-only blast',
            'channel' => 'sms',
            'message' => 'SMS body for bulk',
            'provider' => 'log',
            'status' => 'draft',
        ]);

        app(BulkCampaignSender::class)->send($campaign);

        $campaign->refresh();
        $this->assertSame('completed', $campaign->status);
        $this->assertSame(1, $campaign->sent_count);

        $this->assertDatabaseHas('message_sends', [
            'bulk_sms_campaign_id' => $campaign->id,
            'lead_id' => $lead->id,
            'channel' => 'sms',
            'recipient' => '+447700900100',
        ]);
        $this->assertDatabaseMissing('message_sends', [
            'bulk_sms_campaign_id' => $campaign->id,
            'channel' => 'email',
        ]);
    }

    public function test_bulk_both_channels_sends_email_and_sms(): void
    {
        $account = $this->admin->resolveAccount();
        ['segment' => $segment, 'leads' => $leads] = $this->bulkTestSegment(1);
        $lead = $leads->first();

        $campaign = BulkSmsCampaign::create([
            'account_id' => $account->id,
            'segment_id' => $segment->id,
            'name' => 'Multi-channel blast',
            'channel' => 'both',
            'subject' => 'Hello both',
            'message' => 'Shared body',
            'provider' => 'log',
            'status' => 'draft',
        ]);

        app(BulkCampaignSender::class)->send($campaign);

        $campaign->refresh();
        $this->assertSame('completed', $campaign->status);
        $this->assertSame(2, $campaign->sent_count);

        $this->assertDatabaseHas('message_sends', [
            'bulk_sms_campaign_id' => $campaign->id,
            'lead_id' => $lead->id,
            'channel' => 'email',
        ]);
        $this->assertDatabaseHas('message_sends', [
            'bulk_sms_campaign_id' => $campaign->id,
            'lead_id' => $lead->id,
            'channel' => 'sms',
        ]);
    }

    public function test_bulk_ab_test_splits_variants(): void
    {
        Queue::fake([\App\Jobs\EvaluateAbTestWinnerJob::class]);

        $account = $this->admin->resolveAccount();
        ['segment' => $segment] = $this->bulkTestSegment(4);

        $campaign = BulkSmsCampaign::create([
            'account_id' => $account->id,
            'segment_id' => $segment->id,
            'name' => 'A/B email test',
            'channel' => 'email',
            'subject' => 'Default subject',
            'message' => 'Default body',
            'provider' => 'log',
            'status' => 'draft',
            'ab_test' => [
                'status' => 'pending',
                'split_percent' => 50,
                'wait_minutes' => 60,
                'winner_metric' => 'open',
                'variant_a' => ['subject' => 'Subject A', 'body' => 'Body A'],
                'variant_b' => ['subject' => 'Subject B', 'body' => 'Body B'],
            ],
        ]);

        app(BulkCampaignSender::class)->send($campaign);

        $campaign->refresh();
        $this->assertSame('sending', $campaign->status);
        $this->assertSame('evaluating', $campaign->ab_test['status']);

        $variantA = MessageSend::where('bulk_sms_campaign_id', $campaign->id)->where('ab_variant', 'A')->count();
        $variantB = MessageSend::where('bulk_sms_campaign_id', $campaign->id)->where('ab_variant', 'B')->count();

        $this->assertGreaterThanOrEqual(1, $variantA);
        $this->assertGreaterThanOrEqual(1, $variantB);
        $this->assertSame($variantA + $variantB, MessageSend::where('bulk_sms_campaign_id', $campaign->id)->count());

        $this->assertFalse(app(AbTestService::class)->shouldRunAbTest($campaign->fresh()));
    }

    public function test_bulk_campaign_store_dispatches_send_job(): void
    {
        Queue::fake();
        $account = $this->admin->resolveAccount();
        ['segment' => $segment] = $this->bulkTestSegment(1);

        $this->actingAs($this->admin)
            ->post('/e-delivery/bulk-campaigns', [
                'name' => 'Queued email blast',
                'channel' => 'email',
                'subject' => 'Hi there',
                'message' => 'Bulk via hub',
                'segment_id' => $segment->id,
                'provider' => 'log',
            ])
            ->assertRedirect();

        $campaign = BulkSmsCampaign::where('name', 'Queued email blast')->first();
        $this->assertNotNull($campaign);
        $this->assertSame('email', $campaign->channel);
        $this->assertSame('draft', $campaign->status);

        Queue::assertPushed(\App\Jobs\SendBulkCampaignJob::class, fn ($job) => $job->campaignId === $campaign->id);
    }
}
