<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\MessageEvent;
use App\Models\MessageSend;
use App\Models\User;
use App\Services\Messaging\ListHygieneService;
use App\Services\Messaging\MarketingSuppressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ListHygieneTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        require_once base_path('routes/e-delivery.php');
        registerEDeliveryAdminRoutes();

        if (! Route::has('e-delivery.hygiene.run')) {
            Route::post('e-delivery/hygiene/run', [\App\Http\Controllers\Admin\EDeliveryController::class, 'runHygiene'])
                ->name('e-delivery.hygiene.run');
        }
    }

    protected function enableHygiene(array $overrides = []): void
    {
        $account = $this->admin->resolveAccount();
        $hygiene = app(ListHygieneService::class);

        $account->update([
            'settings' => $hygiene->mergeSettingsIntoAccount($account, array_merge([
                'list_hygiene_enabled' => true,
                'inactive_days_threshold' => 180,
                'hygiene_auto_suppress_bounces' => true,
            ], $overrides)),
        ]);
    }

    protected function hygieneLead(string $email): Lead
    {
        $account = $this->admin->resolveAccount();
        $template = Lead::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->firstOrFail();

        return Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $template->campaign_id,
            'supplier_id' => $template->supplier_id,
            'source_id' => $template->source_id,
            'status' => LeadStatus::Accepted,
            'field_data' => [
                'firstname' => 'Hygiene',
                'lastname' => 'Lead',
                'email' => $email,
            ],
            'sid' => $template->sid,
            'received_at' => now(),
        ]);
    }

    public function test_bounced_lead_is_tagged_for_suppression(): void
    {
        $this->enableHygiene();
        $account = $this->admin->resolveAccount();
        $lead = $this->hygieneLead('bounce-hygiene-'.uniqid().'@example.com');

        $send = MessageSend::create([
            'account_id' => $account->id,
            'lead_id' => $lead->id,
            'channel' => 'email',
            'recipient' => $lead->getField('email'),
            'subject' => 'Hello',
            'body' => 'Body',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        MessageEvent::create([
            'message_send_id' => $send->id,
            'account_id' => $account->id,
            'type' => 'bounce',
            'occurred_at' => now(),
        ]);

        $result = app(ListHygieneService::class)->run($account);

        $this->assertFalse($result['skipped']);
        $this->assertGreaterThanOrEqual(1, $result['bounces_tagged']);
        $this->assertDatabaseHas('lead_tags', [
            'lead_id' => $lead->id,
            'tag' => ListHygieneService::TAG_SUPPRESS_MARKETING,
        ]);
        $this->assertDatabaseHas('lead_tags', [
            'lead_id' => $lead->id,
            'tag' => ListHygieneService::TAG_BOUNCED,
        ]);
        $this->assertDatabaseHas('segments', [
            'account_id' => $account->id,
            'name' => 'bounced',
        ]);
    }

    public function test_esp_bounce_opt_out_lead_is_scrubbed(): void
    {
        $this->enableHygiene();
        $account = $this->admin->resolveAccount();
        $email = 'esp-bounce-'.uniqid().'@example.com';
        $lead = $this->hygieneLead($email);

        app(MarketingSuppressionService::class)->optOut($account->id, 'email', $email, 'esp_bounce');

        app(ListHygieneService::class)->run($account);

        $this->assertDatabaseHas('lead_tags', [
            'lead_id' => $lead->id,
            'tag' => ListHygieneService::TAG_BOUNCED,
        ]);
    }

    public function test_inactive_lead_is_tagged_when_no_recent_engagement(): void
    {
        $this->enableHygiene(['inactive_days_threshold' => 90]);
        $account = $this->admin->resolveAccount();
        $lead = $this->hygieneLead('inactive-'.uniqid().'@example.com');

        MessageSend::create([
            'account_id' => $account->id,
            'lead_id' => $lead->id,
            'channel' => 'email',
            'recipient' => $lead->getField('email'),
            'subject' => 'Hello',
            'body' => 'Body',
            'status' => 'sent',
            'sent_at' => now()->subDays(120),
        ]);

        $result = app(ListHygieneService::class)->run($account);

        $this->assertGreaterThanOrEqual(1, $result['inactive_tagged']);
        $this->assertDatabaseHas('lead_tags', [
            'lead_id' => $lead->id,
            'tag' => ListHygieneService::TAG_INACTIVE,
        ]);
    }

    public function test_dry_run_does_not_tag_leads(): void
    {
        $this->enableHygiene();
        $account = $this->admin->resolveAccount();
        $lead = $this->hygieneLead('dry-run-'.uniqid().'@example.com');

        $send = MessageSend::create([
            'account_id' => $account->id,
            'lead_id' => $lead->id,
            'channel' => 'email',
            'recipient' => $lead->getField('email'),
            'subject' => 'Hello',
            'body' => 'Body',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        MessageEvent::create([
            'message_send_id' => $send->id,
            'account_id' => $account->id,
            'type' => 'bounce',
            'occurred_at' => now(),
        ]);

        $result = app(ListHygieneService::class)->run($account, dryRun: true);

        $this->assertTrue($result['dry_run']);
        $this->assertGreaterThanOrEqual(1, $result['bounces_tagged']);
        $this->assertDatabaseMissing('lead_tags', [
            'lead_id' => $lead->id,
            'tag' => ListHygieneService::TAG_BOUNCED,
        ]);
    }

    public function test_respects_enabled_flag_when_not_forced(): void
    {
        $account = $this->admin->resolveAccount();
        $lead = $this->hygieneLead('disabled-'.uniqid().'@example.com');

        $send = MessageSend::create([
            'account_id' => $account->id,
            'lead_id' => $lead->id,
            'channel' => 'email',
            'recipient' => $lead->getField('email'),
            'subject' => 'Hello',
            'body' => 'Body',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        MessageEvent::create([
            'message_send_id' => $send->id,
            'account_id' => $account->id,
            'type' => 'bounce',
            'occurred_at' => now(),
        ]);

        $result = app(ListHygieneService::class)->run($account);

        $this->assertTrue($result['skipped']);
        $this->assertDatabaseMissing('lead_tags', [
            'lead_id' => $lead->id,
            'tag' => ListHygieneService::TAG_BOUNCED,
        ]);
    }

    public function test_manual_hygiene_run_endpoint_works(): void
    {
        $this->enableHygiene(['inactive_days_threshold' => 90]);
        $account = $this->admin->resolveAccount();
        $lead = $this->hygieneLead('manual-'.uniqid().'@example.com');

        MessageSend::create([
            'account_id' => $account->id,
            'lead_id' => $lead->id,
            'channel' => 'email',
            'recipient' => $lead->getField('email'),
            'subject' => 'Hello',
            'body' => 'Body',
            'status' => 'sent',
            'sent_at' => now()->subDays(120),
        ]);

        $this->actingAs($this->admin)
            ->post('/e-delivery/hygiene/run')
            ->assertRedirect();

        $this->assertDatabaseHas('lead_tags', [
            'lead_id' => $lead->id,
            'tag' => ListHygieneService::TAG_INACTIVE,
        ]);
    }
}
