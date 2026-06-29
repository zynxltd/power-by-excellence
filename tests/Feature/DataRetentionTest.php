<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Lead;
use App\Models\LeadReturn;
use App\Models\MessageEvent;
use App\Models\MessageSend;
use App\Models\User;
use App\Services\Compliance\DataRetentionPolicy;
use App\Services\Compliance\DataRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataRetentionTest extends TestCase
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
        $this->account = Account::where('slug', 'excellence-uk')->first();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function retentionPolicy(array $overrides = []): void
    {
        $this->account->update([
            'settings' => array_merge($this->account->settings ?? [], [
                DataRetentionPolicy::SETTINGS_KEY => array_merge(
                    DataRetentionPolicy::defaults(),
                    $overrides,
                ),
            ]),
        ]);
        $this->account->refresh();
    }

    protected function makeLead(array $overrides = []): Lead
    {
        $template = Lead::withoutGlobalScopes()
            ->where('account_id', $this->account->id)
            ->first();

        return Lead::create(array_merge([
            'account_id' => $this->account->id,
            'campaign_id' => $template->campaign_id,
            'supplier_id' => $template->supplier_id,
            'source_id' => $template->source_id,
            'status' => $template->status,
            'received_at' => now()->subDays(400),
            'field_data' => [
                'firstname' => 'Retain',
                'lastname' => 'Test',
                'email' => 'retain.'.uniqid().'@example.com',
                'phone1' => '07700900400',
            ],
            'ip_address' => '203.0.113.10',
            'user_agent' => 'PHPUnit',
            'metadata' => [],
        ], $overrides));
    }

    public function test_purge_anonymizes_expired_leads_respecting_retention_days(): void
    {
        $this->retentionPolicy([
            'purge_leads' => true,
            'leads_retention_days' => 365,
        ]);

        $expired = $this->makeLead(['received_at' => now()->subDays(400)]);
        $recent = $this->makeLead([
            'received_at' => now()->subDays(30),
            'field_data' => [
                'firstname' => 'Recent',
                'email' => 'recent@example.com',
                'phone1' => '07700900401',
            ],
        ]);

        $result = app(DataRetentionService::class)->purgeAccount($this->account);

        $this->assertSame(1, $result['leads_anonymized']);

        $expired->refresh();
        $recent->refresh();

        $this->assertSame('[redacted]', $expired->field_data['email']);
        $this->assertNull($expired->ip_address);
        $this->assertNotNull($expired->metadata['anonymized_at']);
        $this->assertSame('recent@example.com', $recent->fresh()->field_data['email']);
    }

    public function test_purge_skips_leads_with_open_buyer_disputes(): void
    {
        $this->retentionPolicy([
            'purge_leads' => true,
            'leads_retention_days' => 90,
        ]);

        $disputed = $this->makeLead(['received_at' => now()->subDays(200)]);
        $buyer = Buyer::where('account_id', $this->account->id)->first();

        LeadReturn::create([
            'lead_id' => $disputed->id,
            'buyer_id' => $buyer->id,
            'reason' => 'Invalid contact details',
            'status' => 'pending',
        ]);

        $clear = $this->makeLead([
            'received_at' => now()->subDays(200),
            'field_data' => [
                'firstname' => 'Clear',
                'email' => 'clear@example.com',
                'phone1' => '07700900402',
            ],
        ]);

        $result = app(DataRetentionService::class)->purgeAccount($this->account);

        $this->assertSame(1, $result['leads_anonymized']);

        $disputed->refresh();
        $clear->refresh();

        $this->assertNotSame('[redacted]', $disputed->field_data['email']);
        $this->assertNull($disputed->metadata['anonymized_at'] ?? null);
        $this->assertSame('[redacted]', $clear->field_data['email']);
    }

    public function test_purge_trims_old_logs_and_message_events(): void
    {
        $this->retentionPolicy([
            'purge_logs' => true,
            'logs_retention_days' => 30,
            'purge_message_events' => true,
            'message_events_retention_days' => 30,
        ]);

        $oldLog = AccessLog::create([
            'account_id' => $this->account->id,
            'user_id' => $this->admin->id,
            'action' => 'login',
        ]);
        $oldLog->forceFill([
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ])->saveQuietly();

        AccessLog::create([
            'account_id' => $this->account->id,
            'user_id' => $this->admin->id,
            'action' => 'login',
        ]);

        $send = MessageSend::create([
            'account_id' => $this->account->id,
            'channel' => 'email',
            'recipient' => 'events@example.com',
            'subject' => 'Hi',
            'body' => 'Body',
            'status' => 'sent',
            'sent_at' => now()->subDays(60),
        ]);

        $oldEvent = MessageEvent::create([
            'message_send_id' => $send->id,
            'account_id' => $this->account->id,
            'type' => 'open',
            'occurred_at' => now()->subDays(60),
        ]);
        $oldEvent->forceFill([
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ])->saveQuietly();

        $result = app(DataRetentionService::class)->purgeAccount($this->account);

        $this->assertGreaterThanOrEqual(1, $result['logs_deleted']);
        $this->assertSame(1, $result['message_events_deleted']);
        $this->assertDatabaseMissing('access_logs', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('access_logs', ['account_id' => $this->account->id]);
        $this->assertDatabaseMissing('message_events', ['id' => $oldEvent->id]);
    }

    public function test_purge_skips_categories_when_disabled(): void
    {
        $this->retentionPolicy([
            'purge_leads' => false,
            'purge_logs' => false,
            'purge_message_events' => false,
        ]);

        $lead = $this->makeLead(['received_at' => now()->subDays(500)]);

        AccessLog::create([
            'account_id' => $this->account->id,
            'user_id' => $this->admin->id,
            'action' => 'login',
            'created_at' => now()->subDays(90),
            'updated_at' => now()->subDays(90),
        ]);

        $result = app(DataRetentionService::class)->purgeAccount($this->account);

        $this->assertSame(0, $result['leads_anonymized']);
        $this->assertSame(0, $result['logs_deleted']);
        $this->assertSame(0, $result['message_events_deleted']);
        $this->assertNotSame('[redacted]', $lead->fresh()->field_data['email']);
    }

    public function test_purge_command_processes_active_accounts(): void
    {
        $this->retentionPolicy([
            'purge_leads' => true,
            'leads_retention_days' => 180,
        ]);

        $this->makeLead(['received_at' => now()->subDays(200)]);

        $this->artisan('data-retention:purge', ['--account' => $this->account->id])
            ->assertSuccessful()
            ->expectsOutputToContain('Purge complete');
    }

    public function test_settings_update_persists_retention_policy(): void
    {
        $this->ukHost()
            ->actingAs($this->admin)
            ->put(route('settings.update'), [
                'name' => $this->account->name,
                'timezone' => $this->account->timezone,
                'default_country' => 'GB',
                'default_currency' => 'GBP',
                'data_retention' => [
                    'purge_leads' => true,
                    'leads_retention_days' => 180,
                    'purge_logs' => true,
                    'logs_retention_days' => 45,
                    'purge_message_events' => false,
                    'message_events_retention_days' => 90,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $policy = DataRetentionPolicy::forAccount($this->account->fresh());

        $this->assertTrue($policy['purge_leads']);
        $this->assertSame(180, $policy['leads_retention_days']);
        $this->assertTrue($policy['purge_logs']);
        $this->assertSame(45, $policy['logs_retention_days']);
        $this->assertFalse($policy['purge_message_events']);
    }

    public function test_data_retention_policy_serializes_for_settings_form(): void
    {
        $this->retentionPolicy([
            'purge_leads' => true,
            'leads_retention_days' => 120,
            'purge_logs' => true,
            'logs_retention_days' => 60,
            'purge_message_events' => true,
            'message_events_retention_days' => 30,
        ]);

        $policy = DataRetentionPolicy::forInertia($this->account->fresh());

        $this->assertTrue($policy['purge_leads']);
        $this->assertSame(120, $policy['leads_retention_days']);
        $this->assertTrue($policy['purge_logs']);
        $this->assertSame(60, $policy['logs_retention_days']);
        $this->assertTrue($policy['purge_message_events']);
        $this->assertSame(30, $policy['message_events_retention_days']);
    }
}
