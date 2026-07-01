<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Lead;
use App\Models\MessageSend;
use App\Models\User;
use App\Services\Automation\AutomationSequenceService;
use App\Services\Messaging\MessageSendService;
use App\Services\Messaging\SendTimeOptimizer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SendTimeOptimizerTest extends TestCase
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

        Route::patch('e-delivery/send-time-settings', [\App\Http\Controllers\Admin\EDeliveryController::class, 'updateSendTimeSettings'])
            ->name('e-delivery.send-time-settings.update');
    }

    protected function enableSendTimeOptimization(): void
    {
        $settings = $this->account->settings ?? [];
        $settings['messaging'] = array_merge($settings['messaging'] ?? [], [
            'send_time_optimization' => true,
            'quiet_hours_start' => '21:00',
            'quiet_hours_end' => '08:00',
            'optimal_send_hour' => 9,
        ]);

        $this->account->update(['settings' => $settings]);
        $this->account->refresh();
    }

    protected function usLead(): Lead
    {
        $lead = Lead::first();
        $lead->update([
            'field_data' => array_merge($lead->field_data ?? [], [
                'firstname' => 'Sam',
                'email' => 'sam@example.com',
                'timezone' => 'America/New_York',
            ]),
        ]);

        return $lead->fresh();
    }

    public function test_us_lead_during_quiet_hours_queues_until_local_9am(): void
    {
        $this->enableSendTimeOptimization();
        $lead = $this->usLead();

        // 2:00 AM Eastern (inside 21:00–08:00 quiet window)
        $this->travelTo(Carbon::parse('2026-06-15 06:00:00', 'UTC'));

        $optimizer = app(SendTimeOptimizer::class);
        $scheduledAt = $optimizer->computeSendAt($this->account, $lead);

        $this->assertNotNull($scheduledAt);
        $this->assertSame('09:00', $scheduledAt->copy()->timezone('America/New_York')->format('H:i'));

        $queued = app(MessageSendService::class)->send([
            'account_id' => $this->account->id,
            'lead_id' => $lead->id,
            'channel' => 'email',
            'recipient' => 'sam@example.com',
            'subject' => 'Good morning',
            'body' => 'Queued for local morning window.',
            'track' => false,
        ]);

        $this->assertTrue($queued);
        $this->assertDatabaseHas('message_sends', [
            'lead_id' => $lead->id,
            'status' => 'scheduled',
            'subject' => 'Good morning',
        ]);

        $send = MessageSend::where('lead_id', $lead->id)->first();
        $this->assertNotNull($send->scheduled_at);
        $this->assertSame('09:00', $send->scheduled_at->copy()->timezone('America/New_York')->format('H:i'));
    }

    public function test_outside_quiet_hours_and_after_optimal_hour_sends_immediately(): void
    {
        $this->enableSendTimeOptimization();
        $lead = $this->usLead();

        // 10:00 AM Eastern
        $this->travelTo(Carbon::parse('2026-06-15 14:00:00', 'UTC'));

        $scheduledAt = app(SendTimeOptimizer::class)->computeSendAt($this->account, $lead);
        $this->assertNull($scheduledAt);

        $sent = app(MessageSendService::class)->send([
            'account_id' => $this->account->id,
            'lead_id' => $lead->id,
            'channel' => 'email',
            'recipient' => 'sam@example.com',
            'subject' => 'Immediate send',
            'body' => 'Sent right away.',
            'track' => false,
        ]);

        $this->assertTrue($sent);
        $this->assertDatabaseHas('message_sends', [
            'lead_id' => $lead->id,
            'status' => 'sent',
            'subject' => 'Immediate send',
        ]);
    }

    public function test_scheduled_send_dispatches_when_due(): void
    {
        $this->enableSendTimeOptimization();
        $lead = $this->usLead();

        $this->travelTo(Carbon::parse('2026-06-15 06:00:00', 'UTC'));

        app(MessageSendService::class)->send([
            'account_id' => $this->account->id,
            'lead_id' => $lead->id,
            'channel' => 'email',
            'recipient' => 'sam@example.com',
            'subject' => 'Deferred',
            'body' => 'Will send at 9am local.',
            'track' => false,
        ]);

        $send = MessageSend::where('lead_id', $lead->id)->first();
        $this->assertSame('scheduled', $send->status);

        $this->artisan('messaging:process-scheduled')->assertSuccessful();
        $send->refresh();
        $this->assertSame('scheduled', $send->status);

        $this->travelTo($send->scheduled_at);
        $this->artisan('messaging:process-scheduled')->assertSuccessful();

        $send->refresh();
        $this->assertSame('sent', $send->status);
        $this->assertNotNull($send->sent_at);
    }

    public function test_automation_sequence_defers_send_through_message_service(): void
    {
        $this->enableSendTimeOptimization();
        $lead = $this->usLead();

        $this->travelTo(Carbon::parse('2026-06-15 06:00:00', 'UTC'));

        $sequence = \App\Models\AutomationSequence::create([
            'account_id' => $this->account->id,
            'name' => 'Morning drip',
            'trigger_event' => 'on_lead_received',
            'status' => 'active',
        ]);

        \App\Models\AutomationSequenceStep::create([
            'automation_sequence_id' => $sequence->id,
            'sort_order' => 0,
            'action' => 'send',
            'delay_minutes' => 0,
            'channel' => 'email',
            'config' => [
                'subject' => 'Journey deferred',
                'body' => 'Queued by optimizer.',
                'to_field' => 'email',
            ],
        ]);

        app(AutomationSequenceService::class)->enrollLead($lead, $sequence->fresh('steps'));

        $this->assertDatabaseHas('message_sends', [
            'lead_id' => $lead->id,
            'status' => 'scheduled',
            'subject' => 'Journey deferred',
        ]);
    }

    public function test_send_time_settings_can_be_updated_from_e_delivery(): void
    {
        $this->actingAs($this->admin)
            ->patch('/e-delivery/send-time-settings', [
                'send_time_optimization' => true,
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
                'optimal_send_hour' => 10,
            ])
            ->assertRedirect();

        $messaging = $this->account->fresh()->settings['messaging'] ?? [];

        $this->assertTrue($messaging['send_time_optimization']);
        $this->assertSame('22:00', $messaging['quiet_hours_start']);
        $this->assertSame('07:00', $messaging['quiet_hours_end']);
        $this->assertSame(10, $messaging['optimal_send_hour']);
    }
}
