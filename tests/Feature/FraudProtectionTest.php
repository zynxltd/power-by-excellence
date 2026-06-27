<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Billing\FraudProtectionService;
use App\Services\Leads\LeadPipeline;
use App\Services\Validation\ValidationService;
use App\Support\Tenancy\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FraudProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        AccountContext::clear();
        session()->forget('current_account_id');
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->account = Account::where('slug', 'excellence-uk')->first();
    }

    public function test_growth_plan_is_entitled_by_default_from_seeder(): void
    {
        $summary = app(FraudProtectionService::class)->summary($this->account);

        $this->assertSame('growth', $summary['plan']);
        $this->assertTrue($summary['entitled']);
        $this->assertTrue($summary['included']);
        $this->assertTrue($summary['supports_residential_proxy']);
        $this->assertFalse($summary['supports_url_scanner']);
    }

    public function test_starter_without_addon_is_not_entitled(): void
    {
        $settings = $this->account->settings ?? [];
        $settings['subscription_plan'] = 'starter';
        $settings['fraud_protection'] = ['enabled' => false, 'included' => false];
        $this->account->update(['settings' => $settings]);

        $fraud = app(FraudProtectionService::class);

        $this->assertFalse($fraud->isEntitled($this->account->fresh()));
    }

    public function test_starter_ignores_stale_fraud_enabled_flag(): void
    {
        $settings = $this->account->settings ?? [];
        $settings['subscription_plan'] = 'starter';
        $settings['fraud_protection'] = ['enabled' => true, 'included' => false];
        $this->account->update(['settings' => $settings]);

        $fraud = app(FraudProtectionService::class);
        $summary = $fraud->summary($this->account->fresh());

        $this->assertFalse($fraud->isPlanEntitled($this->account->fresh()));
        $this->assertFalse($summary['plan_entitled']);
    }

    public function test_super_admin_override_does_not_mark_starter_plan_entitled(): void
    {
        $settings = $this->account->settings ?? [];
        $settings['subscription_plan'] = 'starter';
        $settings['fraud_protection'] = ['enabled' => false, 'included' => false];
        $this->account->update(['settings' => $settings]);

        $superAdmin = \App\Models\User::where('email', 'admin@powerbyexcellence.test')->first();
        $this->actingAs($superAdmin);

        $summary = app(FraudProtectionService::class)->summary($this->account->fresh());

        $this->assertTrue($summary['entitled']);
        $this->assertTrue($summary['admin_override']);
        $this->assertFalse($summary['plan_entitled']);
    }

    public function test_super_admin_sees_all_fraud_detection_features_on_starter_without_addon(): void
    {
        $settings = $this->account->settings ?? [];
        $settings['subscription_plan'] = 'starter';
        $settings['fraud_protection'] = ['enabled' => false, 'included' => false];
        $settings['validation_integration'] = array_merge($settings['validation_integration'] ?? [], [
            'enabled' => true,
            'provider' => 'ipqs',
            'email_validation' => true,
        ]);
        $this->account->update(['settings' => $settings]);

        $superAdmin = \App\Models\User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->actingAs($superAdmin);

        $fraud = app(FraudProtectionService::class);
        $account = $this->account->fresh();

        $this->assertFalse($fraud->isPlanEntitled($account));
        $this->assertTrue($fraud->isEntitled($account));
        $this->assertTrue($fraud->supportsResidentialProxy($account));
        $this->assertFalse($fraud->supportsUrlScanner($account));
        $this->assertTrue($fraud->canValidateLead($account));

        Http::fake([
            'www.ipqualityscore.com/api/json/email/*' => Http::response([
                'success' => true,
                'valid' => true,
                'fraud_score' => 5,
                'disposable' => false,
                'honeypot' => false,
                'spam_trap_score' => 'none',
            ]),
            'www.ipqualityscore.com/api/json/phone/*' => Http::response([
                'success' => true,
                'valid' => true,
                'fraud_score' => 5,
                'active' => true,
                'line_type' => 'mobile',
            ]),
            'www.ipqualityscore.com/api/json/ip/*' => Http::response([
                'success' => true,
                'fraud_score' => 5,
                'proxy' => false,
                'vpn' => false,
                'tor' => false,
            ]),
        ]);

        config(['validation.ipqs.api_key' => 'test-key']);

        $campaign = Campaign::withoutGlobalScopes()->where('reference', 'auto-insurance-uk')->first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Pending,
            'field_data' => ['email' => 'user@example.com', 'phone1' => '07700900123', 'zipcode' => 'SW1A 1AA', 'lastname' => 'Test'],
            'received_at' => now(),
        ]);

        $error = app(ValidationService::class)->validateLead($lead, $campaign);

        $this->assertNull($error);
        $this->assertSame('ipqs', $lead->metadata['email_validation']['provider'] ?? null);
        $this->assertSame(0, $fraud->usageCount($account->fresh()));
    }

    public function test_super_admin_bypasses_monthly_fraud_cap(): void
    {
        $settings = $this->account->settings ?? [];
        $settings['fraud_protection'] = [
            'enabled' => true,
            'included' => true,
            'usage_count' => 25000,
            'usage_period' => now()->format('Y-m'),
        ];
        $this->account->update(['settings' => $settings]);

        $superAdmin = \App\Models\User::where('email', 'admin@powerbyexcellence.test')->first();
        $this->actingAs($superAdmin);

        Http::fake([
            'www.ipqualityscore.com/api/json/email/*' => Http::response([
                'success' => true,
                'valid' => true,
                'fraud_score' => 5,
                'disposable' => false,
                'honeypot' => false,
                'spam_trap_score' => 'none',
            ]),
            'www.ipqualityscore.com/api/json/phone/*' => Http::response([
                'success' => true,
                'valid' => true,
                'fraud_score' => 5,
                'active' => true,
                'line_type' => 'mobile',
            ]),
            'www.ipqualityscore.com/api/json/ip/*' => Http::response([
                'success' => true,
                'fraud_score' => 5,
                'proxy' => false,
                'vpn' => false,
                'tor' => false,
            ]),
        ]);

        config(['validation.ipqs.api_key' => 'test-key']);

        $campaign = Campaign::withoutGlobalScopes()->where('reference', 'auto-insurance-uk')->first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Pending,
            'field_data' => ['email' => 'user@example.com', 'phone1' => '07700900123', 'zipcode' => 'SW1A 1AA', 'lastname' => 'Test'],
            'received_at' => now(),
        ]);

        $error = app(ValidationService::class)->validateLead($lead, $campaign);

        $this->assertNull($error);
        $this->assertSame('ipqs', $lead->metadata['email_validation']['provider'] ?? null);
    }

    public function test_starter_addon_is_not_available(): void
    {
        $settings = app(FraudProtectionService::class)->provisionSettingsForPlan(
            $this->account->settings ?? [],
            'starter',
            true,
        );
        $this->account->update(['settings' => $settings]);

        $fraud = app(FraudProtectionService::class);
        $summary = $fraud->summary($this->account->fresh());

        $this->assertFalse($fraud->isEntitled($this->account->fresh()));
        $this->assertFalse($summary['addon_available']);
        $this->assertFalse($summary['entitled']);
    }

    public function test_validation_skipped_when_not_entitled(): void
    {
        $settings = $this->account->settings ?? [];
        $settings['subscription_plan'] = 'starter';
        $settings['fraud_protection'] = ['enabled' => false];
        $settings['validation_integration'] = array_merge($settings['validation_integration'] ?? [], [
            'enabled' => true,
            'provider' => 'ipqs',
            'email_validation' => true,
        ]);
        $this->account->update(['settings' => $settings]);

        $campaign = Campaign::withoutGlobalScopes()->where('reference', 'auto-insurance-uk')->first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Pending,
            'field_data' => ['email' => 'user@invalid.demo', 'phone1' => '07700900123', 'zipcode' => 'SW1A 1AA', 'lastname' => 'Test'],
            'received_at' => now(),
        ]);

        $error = app(ValidationService::class)->validateLead($lead, $campaign);

        $this->assertNull($error);
        $this->assertEmpty($lead->fresh()->metadata['email_validation'] ?? null);
    }

    public function test_cap_exceeded_skips_fraud_checks(): void
    {
        $settings = $this->account->settings ?? [];
        $settings['fraud_protection'] = [
            'enabled' => true,
            'included' => true,
            'usage_count' => 25000,
            'usage_period' => now()->format('Y-m'),
        ];
        $this->account->update(['settings' => $settings]);

        Http::fake([
            'www.ipqualityscore.com/api/json/email/*' => Http::response([
                'success' => true,
                'valid' => false,
                'fraud_score' => 99,
            ]),
        ]);

        $campaign = Campaign::withoutGlobalScopes()->where('reference', 'auto-insurance-uk')->first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Pending,
            'field_data' => ['email' => 'bad@example.com', 'phone1' => '07700900123', 'zipcode' => 'SW1A 1AA', 'lastname' => 'Test'],
            'received_at' => now(),
        ]);

        $error = app(ValidationService::class)->validateLead($lead, $campaign);

        $this->assertNull($error);
        $this->assertEmpty($lead->fresh()->metadata['email_validation'] ?? null);
    }

    public function test_super_admin_starter_plan_does_not_enable_fraud(): void
    {
        $superAdmin = \App\Models\User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'powerbyexcellence.test'])
            ->actingAs($superAdmin)
            ->put(route('accounts.billing.update', $this->account), [
                'monthly_rent' => 299,
                'subscription_plan' => 'starter',
                'billing_status' => 'active',
            ])
            ->assertRedirect();

        $fresh = $this->account->fresh();
        $this->assertSame('starter', $fresh->settings['subscription_plan']);
        $this->assertFalse($fresh->settings['fraud_protection']['enabled']);
    }
}
