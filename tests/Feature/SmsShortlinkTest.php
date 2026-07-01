<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AutomationSequence;
use App\Models\AutomationSequenceStep;
use App\Models\Lead;
use App\Models\MessageEvent;
use App\Models\MessageSend;
use App\Models\MessageShortLink;
use App\Models\User;
use App\Services\Automation\AutomationSequenceService;
use App\Services\Messaging\MessageSendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SmsShortlinkTest extends TestCase
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
        $this->account = $this->admin->resolveAccount();

        require_once base_path('routes/e-delivery.php');
        registerEDeliveryAdminRoutes();

        if (! Route::has('messaging.shortlink.redirect')) {
            Route::get('/s/{slug}', [\App\Http\Controllers\MessageTrackingController::class, 'shortlinkRedirect'])
                ->name('messaging.shortlink.redirect');
        }

        Route::patch('e-delivery/shortlink-settings', [\App\Http\Controllers\Admin\EDeliveryController::class, 'updateShortlinkSettings'])
            ->name('e-delivery.shortlink-settings.update');
    }

    protected function enableShortlinks(): void
    {
        $settings = $this->account->settings ?? [];
        $settings['messaging'] = array_merge($settings['messaging'] ?? [], [
            'sms_shortlinks_enabled' => true,
        ]);
        $this->account->update(['settings' => $settings]);
        $this->account->refresh();
    }

    protected function smsLead(): Lead
    {
        $lead = Lead::first();
        $lead->update([
            'field_data' => array_merge($lead->field_data ?? [], [
                'firstname' => 'Riley',
                'phone1' => '+15551234567',
            ]),
        ]);

        return $lead->fresh();
    }

    public function test_sms_urls_rewritten_when_shortlinks_enabled(): void
    {
        $this->enableShortlinks();
        $lead = $this->smsLead();

        app(MessageSendService::class)->send([
            'account_id' => $this->account->id,
            'lead_id' => $lead->id,
            'channel' => 'sms',
            'recipient' => '+15551234567',
            'body' => 'Hi Riley, visit https://example.com/offers today.',
            'track' => false,
        ]);

        $send = MessageSend::where('lead_id', $lead->id)->latest('id')->first();

        $this->assertStringContainsString('/s/', $send->body);
        $this->assertStringNotContainsString('https://example.com/offers', $send->body);
        $this->assertDatabaseHas('message_short_links', [
            'message_send_id' => $send->id,
            'destination_url' => 'https://example.com/offers',
        ]);
    }

    public function test_shortlink_redirect_logs_click_and_redirects(): void
    {
        $this->enableShortlinks();
        $lead = $this->smsLead();

        app(MessageSendService::class)->send([
            'account_id' => $this->account->id,
            'lead_id' => $lead->id,
            'channel' => 'sms',
            'recipient' => '+15551234567',
            'body' => 'Go to https://example.com/page',
            'track' => false,
        ]);

        $link = MessageShortLink::first();

        $this->get('/s/'.$link->slug)
            ->assertRedirect('https://example.com/page');

        $link->refresh();
        $this->assertSame(1, (int) $link->click_count);

        $this->assertDatabaseHas('message_events', [
            'message_send_id' => $link->message_send_id,
            'type' => 'click',
            'url' => 'https://example.com/page',
        ]);

        $event = MessageEvent::where('message_send_id', $link->message_send_id)->where('type', 'click')->first();
        $this->assertSame('sms_shortlink', $event->meta['via'] ?? null);
    }

    public function test_disabled_shortlinks_send_raw_urls(): void
    {
        $lead = $this->smsLead();
        $body = 'Plain link https://example.com/raw';

        app(MessageSendService::class)->send([
            'account_id' => $this->account->id,
            'lead_id' => $lead->id,
            'channel' => 'sms',
            'recipient' => '+15551234567',
            'body' => $body,
            'track' => false,
        ]);

        $send = MessageSend::where('lead_id', $lead->id)->latest('id')->first();

        $this->assertSame($body, $send->body);
        $this->assertDatabaseCount('message_short_links', 0);
    }

    public function test_duplicate_url_in_same_send_reuses_slug(): void
    {
        $this->enableShortlinks();
        $lead = $this->smsLead();

        app(MessageSendService::class)->send([
            'account_id' => $this->account->id,
            'lead_id' => $lead->id,
            'channel' => 'sms',
            'recipient' => '+15551234567',
            'body' => 'First https://example.com/dup and again https://example.com/dup',
            'track' => false,
        ]);

        $send = MessageSend::where('lead_id', $lead->id)->latest('id')->first();
        $links = MessageShortLink::where('message_send_id', $send->id)->get();

        $this->assertCount(1, $links);
        $this->assertSame(2, substr_count($send->body, '/s/'.$links->first()->slug));
    }

    public function test_automation_sms_journey_step_tracks_shortlinks(): void
    {
        $this->enableShortlinks();
        $lead = $this->smsLead();

        $sequence = AutomationSequence::create([
            'account_id' => $this->account->id,
            'name' => 'SMS journey',
            'trigger_event' => 'on_lead_received',
            'status' => 'active',
        ]);

        $step = AutomationSequenceStep::create([
            'automation_sequence_id' => $sequence->id,
            'sort_order' => 0,
            'action' => 'send',
            'delay_minutes' => 0,
            'channel' => 'sms',
            'config' => [
                'body' => 'Journey link https://example.com/journey',
                'to_field' => 'phone1',
            ],
        ]);

        app(AutomationSequenceService::class)->enrollLead($lead, $sequence->fresh('steps'));

        $send = MessageSend::where('lead_id', $lead->id)->first();
        $this->assertStringContainsString('/s/', $send->body);

        $link = MessageShortLink::where('message_send_id', $send->id)->first();
        $this->assertNotNull($link);
        $this->assertSame($step->id, (int) $link->automation_sequence_step_id);

        $this->get('/s/'.$link->slug)->assertRedirect('https://example.com/journey');
        $this->assertDatabaseHas('message_events', [
            'message_send_id' => $send->id,
            'type' => 'click',
        ]);
    }

    public function test_shortlink_settings_can_be_updated(): void
    {
        $this->actingAs($this->admin)
            ->patch('/e-delivery/shortlink-settings', [
                'sms_shortlinks_enabled' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($this->account->fresh()->settings['messaging']['sms_shortlinks_enabled']);
    }
}
