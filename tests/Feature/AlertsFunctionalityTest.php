<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\EventAlert;
use App\Models\EventAlertFire;
use App\Models\Lead;
use App\Models\User;
use App\Services\Alerts\EventAlertService;
use App\Services\Billing\BuyerCreditAlertService;
use App\Services\Messaging\MessagingGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class AlertsFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $ukAdmin;

    protected User $usAdmin;

    protected Account $ukAccount;

    protected Account $usAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->usAdmin = User::where('email', 'us@powerbyexcellence.test')->first();
        $this->ukAccount = Account::where('slug', 'excellence-uk')->first();
        $this->usAccount = Account::where('slug', 'partner-solar-us')->first();
    }

    protected function alertService(): EventAlertService
    {
        return app(EventAlertService::class);
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_delivery_success_rate_uses_campaign_account_scope(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $delivery = Delivery::where('campaign_id', $campaign->id)->first();

        foreach (['success', 'failed', 'failed'] as $status) {
            $lead = Lead::create([
                'account_id' => $this->ukAccount->id,
                'campaign_id' => $campaign->id,
                'status' => 'unsold',
                'field_data' => ['email' => uniqid('alert-', true).'@test.test'],
                'received_at' => now(),
            ]);

            DeliveryLog::create([
                'lead_id' => $lead->id,
                'delivery_id' => $delivery->id,
                'buyer_id' => $delivery->buyer_id,
                'status' => $status,
                'created_at' => now(),
            ]);
        }

        $alert = EventAlert::create([
            'account_id' => $this->ukAccount->id,
            'name' => 'Low delivery success',
            'metric' => 'delivery_success_rate_24h',
            'operator' => 'lt',
            'threshold' => 50,
            'channel' => 'email',
            'status' => 'active',
            'config' => ['email' => 'ops@excellence.test', 'cooldown_minutes' => 5],
        ]);

        $messaging = Mockery::mock(MessagingGateway::class);
        $messaging->shouldReceive('sendEmail')->once()->andReturn(true);
        $this->app->instance(MessagingGateway::class, $messaging);

        $this->alertService()->evaluateForAccount($this->ukAccount->id);

        $fire = EventAlertFire::where('event_alert_id', $alert->id)->first();
        $this->assertNotNull($fire);
        $this->assertEquals(33.3, (float) $fire->value);
        $this->assertStringContainsString('Delivery success (24h) is 33.3%', $fire->message);
        $this->assertStringContainsString('threshold below 50%', $fire->message);
    }

    public function test_quarantined_count_alert_fires_with_readable_message(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();

        Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'status' => 'quarantined',
            'field_data' => ['email' => 'quarantine-alert@test.test'],
            'received_at' => now(),
        ]);

        $alert = EventAlert::create([
            'account_id' => $this->ukAccount->id,
            'name' => 'Quarantine backlog',
            'metric' => 'quarantined_count',
            'operator' => 'gte',
            'threshold' => 1,
            'channel' => 'email',
            'status' => 'active',
            'config' => ['email' => 'ops@excellence.test', 'cooldown_minutes' => 5],
        ]);

        $messaging = Mockery::mock(MessagingGateway::class);
        $messaging->shouldReceive('sendEmail')->once()->andReturn(true);
        $this->app->instance(MessagingGateway::class, $messaging);

        $this->alertService()->evaluateForAccount($this->ukAccount->id);

        $this->assertDatabaseHas('event_alert_fires', [
            'event_alert_id' => $alert->id,
            'metric' => 'quarantined_count',
            'status' => 'sent',
        ]);
    }

    public function test_alert_cooldown_prevents_duplicate_fires(): void
    {
        $alert = EventAlert::create([
            'account_id' => $this->ukAccount->id,
            'name' => 'High reject rate',
            'metric' => 'reject_rate_24h',
            'operator' => 'gte',
            'threshold' => 0,
            'channel' => 'email',
            'status' => 'active',
            'config' => ['email' => 'ops@excellence.test', 'cooldown_minutes' => 60],
        ]);

        $messaging = Mockery::mock(MessagingGateway::class);
        $messaging->shouldReceive('sendEmail')->once()->andReturn(true);
        $this->app->instance(MessagingGateway::class, $messaging);

        $this->alertService()->evaluateForAccount($this->ukAccount->id);
        $this->alertService()->evaluateForAccount($this->ukAccount->id);

        $this->assertSame(1, EventAlertFire::where('event_alert_id', $alert->id)->count());
    }

    public function test_uk_admin_cannot_delete_other_tenant_alert(): void
    {
        $usAlert = EventAlert::withoutGlobalScopes()->create([
            'account_id' => $this->usAccount->id,
            'name' => 'US-only alert',
            'metric' => 'leads_today',
            'operator' => 'gt',
            'threshold' => 100,
            'channel' => 'email',
            'status' => 'active',
            'config' => ['email' => 'us@test.test'],
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->delete(route('automation.alerts.destroy', $usAlert))
            ->assertNotFound();
    }

    public function test_automation_page_scopes_alert_fires_to_tenant(): void
    {
        $ukAlert = EventAlert::create([
            'account_id' => $this->ukAccount->id,
            'name' => 'UK alert',
            'metric' => 'leads_today',
            'operator' => 'gt',
            'threshold' => 1,
            'channel' => 'email',
            'status' => 'active',
            'config' => ['email' => 'uk@test.test'],
        ]);

        $usAlert = EventAlert::withoutGlobalScopes()->create([
            'account_id' => $this->usAccount->id,
            'name' => 'US alert',
            'metric' => 'leads_today',
            'operator' => 'gt',
            'threshold' => 1,
            'channel' => 'email',
            'status' => 'active',
            'config' => ['email' => 'us@test.test'],
        ]);

        EventAlertFire::create([
            'event_alert_id' => $ukAlert->id,
            'account_id' => $this->ukAccount->id,
            'metric' => 'leads_today',
            'value' => 5,
            'threshold' => 1,
            'channel' => 'email',
            'status' => 'sent',
            'message' => 'UK fire',
        ]);

        EventAlertFire::create([
            'event_alert_id' => $usAlert->id,
            'account_id' => $this->usAccount->id,
            'metric' => 'leads_today',
            'value' => 8,
            'threshold' => 1,
            'channel' => 'email',
            'status' => 'sent',
            'message' => 'US fire',
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get('http://excellence-uk.powerbyexcellence.test/automation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('recentAlertFires', 1)
                ->where('recentAlertFires.0.message', 'UK fire')
                ->where('eventAlerts.0.name', 'UK alert')
            );
    }

    public function test_automation_metrics_use_human_labels(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get('http://excellence-uk.powerbyexcellence.test/automation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('metrics', 9)
                ->where('metrics.0', ['value' => 'leads_today', 'label' => 'Leads today'])
                ->where('metrics.4.value', 'delivery_success_rate_24h')
                ->where('metrics.4.label', 'Delivery success (24h)')
            );
    }

    public function test_low_credit_alert_uses_buyer_and_account_thresholds(): void
    {
        Mail::fake();

        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();
        $this->ukAccount->update([
            'settings' => array_merge($this->ukAccount->settings ?? [], ['default_low_credit_alert' => 250]),
        ]);
        $buyer->update([
            'credit_balance' => 300,
            'settings' => [],
        ]);

        $service = app(BuyerCreditAlertService::class);
        $this->assertSame(250.0, $service->thresholdFor($buyer->fresh()));

        $buyer->update(['credit_balance' => 200]);
        $service->checkAfterDebit($buyer->fresh());
        Mail::assertSent(\App\Mail\BuyerLowCreditMail::class);

        Mail::fake();
        $buyer->update(['credit_balance' => 150]);
        $service->checkAfterDebit($buyer->fresh());
        Mail::assertNothingSent();
    }

    public function test_low_credit_alert_respects_suppress_flag(): void
    {
        Mail::fake();

        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();
        $buyer->update([
            'credit_balance' => 10,
            'settings' => ['low_credit_alert' => 50],
        ]);

        app(BuyerCreditAlertService::class)->checkAfterDebit($buyer->fresh(), suppressAlerts: true);

        Mail::assertNothingSent();
    }

    public function test_delivery_success_defaults_to_100_when_no_logs(): void
    {
        $isolated = Account::create([
            'name' => 'Alert Isolated',
            'slug' => 'alert-isolated',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
        ]);

        $alert = EventAlert::create([
            'account_id' => $isolated->id,
            'name' => 'False positive guard',
            'metric' => 'delivery_success_rate_24h',
            'operator' => 'lt',
            'threshold' => 50,
            'channel' => 'email',
            'status' => 'active',
            'config' => ['email' => 'ops@test.test'],
        ]);

        $messaging = Mockery::mock(MessagingGateway::class);
        $messaging->shouldNotReceive('sendEmail');
        $this->app->instance(MessagingGateway::class, $messaging);

        $this->app->instance(MessagingGateway::class, $messaging);

        $this->alertService()->evaluateForAccount($isolated->id);

        $this->assertSame(0, EventAlertFire::where('event_alert_id', $alert->id)->count());
    }
}
