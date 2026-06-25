<?php

namespace Tests\Feature;

use App\Models\AutomationSequence;
use App\Models\BulkSmsCampaign;
use App\Models\Campaign;
use App\Models\EventAlert;
use App\Models\HelpArticle;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LeadByteParityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->seed(\Database\Seeders\HelpArticleSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
    }

    public function test_public_help_centre(): void
    {
        $this->assertGreaterThan(10, HelpArticle::count());

        $this->get(route('help.index'))->assertOk();
        $article = HelpArticle::first();
        $this->get(route('help.show', $article->slug))->assertOk();
    }

    public function test_user_support_ticket_flow(): void
    {
        $this->actingAs($this->admin)
            ->post(route('support.store'), [
                'subject' => 'Need help with routing',
                'body' => 'How do I configure ping-post?',
                'priority' => 'normal',
            ])
            ->assertRedirect();

        $ticket = SupportTicket::where('subject', 'Need help with routing')->first();
        $this->assertNotNull($ticket);

        $this->actingAs($this->admin)
            ->get(route('support.show', $ticket))
            ->assertOk();
    }

    public function test_admin_support_and_automation_pages(): void
    {
        $routes = [
            '/support/manage',
            '/automation',
            '/logs/security',
        ];

        foreach ($routes as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_automation_sequence_and_alert_creation(): void
    {
        $campaign = Campaign::first();

        $this->actingAs($this->admin)
            ->post(route('automation.sequences.store'), [
                'name' => 'Sold follow-up',
                'campaign_id' => $campaign->id,
                'trigger_event' => 'on_lead_sold',
                'steps' => [
                    ['delay_minutes' => 0, 'channel' => 'email', 'config' => ['subject' => 'Thanks', 'body' => 'Hi {{firstname}}']],
                    ['delay_minutes' => 60, 'channel' => 'sms', 'config' => ['body' => 'Follow up SMS']],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('automation_sequences', ['name' => 'Sold follow-up']);

        $this->actingAs($this->admin)
            ->post(route('automation.alerts.store'), [
                'name' => 'Low success rate',
                'metric' => 'delivery_success_rate_24h',
                'operator' => 'lt',
                'threshold' => 50,
                'channel' => 'email',
                'config' => ['email' => 'alerts@test.com'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('event_alerts', ['name' => 'Low success rate']);
    }

    public function test_bulk_sms_campaign(): void
    {
        $this->actingAs($this->admin)
            ->post(route('automation.bulk-sms.store'), [
                'name' => 'Reactivation blast',
                'message' => 'Hi {{firstname}}, still interested?',
                'filter' => ['status' => 'unsold', 'days' => 30, 'has_phone' => true],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bulk_sms_campaigns', ['name' => 'Reactivation blast']);
    }

    public function test_suppression_csv_import(): void
    {
        $campaign = Campaign::first();
        $csv = "email\ntest@blocked.com\nother@blocked.com\n";
        $file = UploadedFile::fake()->createWithContent('suppression.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('imports.store'), [
                'type' => 'suppression',
                'campaign_id' => $campaign->id,
                'field' => 'email',
                'file' => $file,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('suppression_hashes', 2);
    }

    public function test_validation_config_enforced(): void
    {
        $campaign = Campaign::with('fields')->first();
        $campaign->update(['validation_config' => ['require_email' => true, 'block_disposable_email' => true]]);

        $baseFields = [];
        foreach ($campaign->fields as $field) {
            $baseFields[$field->name] = match ($field->name) {
                'email' => 'valid@example.com',
                'phone1' => '07700900123',
                'zipcode' => 'SW1A 1AA',
                default => 'Test',
            };
        }
        unset($baseFields['email']);

        $validator = app(\App\Services\Leads\LeadValidator::class);
        $lead = new \App\Models\Lead(['campaign_id' => $campaign->id, 'field_data' => $baseFields]);

        $this->assertStringContainsString('email', strtolower($validator->validate($lead, $campaign) ?? ''));

        $baseFields['email'] = 'test@mailinator.com';
        $lead->field_data = $baseFields;
        $this->assertStringContainsString('Disposable', $validator->validate($lead, $campaign));
    }

    public function test_schedule_service(): void
    {
        $service = app(\App\Services\Scheduling\ScheduleService::class);

        $this->assertTrue($service->isWithinSchedule(null));
        $this->assertTrue($service->isWithinSchedule([
            'timezone' => 'Europe/London',
            'windows' => [['day' => 'all', 'start' => '00:00', 'end' => '23:59']],
        ]));
    }
}
