<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Leads\LeadPipeline;
use App\Services\Validation\IpqsValidationProvider;
use App\Services\Validation\ValidationProviderResolver;
use App\Support\Tenancy\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IpqsValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AccountContext::clear();
        session()->forget('current_account_id');
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_ipqs_email_validation_passes_clean_address(): void
    {
        Http::fake([
            'www.ipqualityscore.com/api/json/email/*' => Http::response([
                'success' => true,
                'valid' => true,
                'disposable' => false,
                'fraud_score' => 12,
            ]),
        ]);

        $provider = new IpqsValidationProvider(['api_key' => 'test-key', 'fraud_score_threshold' => 85]);
        $result = $provider->validateEmail('good@example.com');

        $this->assertTrue($result->passed);
        $this->assertSame('ipqs', $result->meta['provider']);
        $this->assertSame(12, $result->meta['fraud_score']);
    }

    public function test_ipqs_email_fails_disposable_address(): void
    {
        Http::fake([
            'www.ipqualityscore.com/api/json/email/*' => Http::response([
                'success' => true,
                'valid' => true,
                'disposable' => true,
                'fraud_score' => 10,
            ]),
        ]);

        $provider = new IpqsValidationProvider([
            'api_key' => 'test-key',
            'fraud_score_threshold' => 85,
            'block_disposable_email' => true,
        ]);

        $result = $provider->validateEmail('temp@mailinator.com');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('Disposable', $result->reason);
    }

    public function test_ipqs_ip_fails_vpn_detection(): void
    {
        Http::fake([
            'www.ipqualityscore.com/api/json/ip/*' => Http::response([
                'success' => true,
                'fraud_score' => 42,
                'proxy' => false,
                'vpn' => true,
                'tor' => false,
                'bot_status' => false,
            ]),
        ]);

        $provider = new IpqsValidationProvider([
            'api_key' => 'test-key',
            'fraud_score_threshold' => 85,
            'block_vpn' => true,
        ]);

        $result = $provider->validateIp('203.0.113.10');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('VPN', $result->reason);
    }

    public function test_ipqs_url_validation_blocks_malware(): void
    {
        Http::fake([
            'www.ipqualityscore.com/api/json/url/*' => Http::response([
                'success' => true,
                'unsafe' => false,
                'risk_score' => 10,
                'phishing' => false,
                'malware' => true,
                'suspicious' => false,
                'parking' => false,
                'spamming' => false,
                'domain' => 'evil.test',
            ]),
        ]);

        $provider = new IpqsValidationProvider([
            'api_key' => 'test-key',
            'url_risk_threshold' => 85,
            'block_malware_url' => true,
        ]);

        $result = $provider->validateUrl('https://evil.test/payload');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('Malware', $result->reason);
    }

    public function test_ipqs_ip_uses_user_agent_when_configured(): void
    {
        Http::fake(function ($request) {
            $this->assertStringContainsString('user_agent=', urldecode((string) $request->url()));

            return Http::response([
                'success' => true,
                'fraud_score' => 5,
                'proxy' => false,
                'vpn' => false,
                'tor' => false,
                'bot_status' => false,
            ]);
        });

        $provider = new IpqsValidationProvider([
            'api_key' => 'test-key',
            'pass_user_agent' => true,
        ]);

        $context = new \App\Services\Validation\ValidationContext(userAgent: 'Mozilla/5.0 Test');
        $result = $provider->validateIp('8.8.8.8', $context);

        $this->assertTrue($result->passed);
    }

    public function test_pipeline_quarantines_on_ipqs_ip_fraud_failure(): void
    {
        $campaign = Campaign::withoutGlobalScopes()->with('fields')->where('reference', 'auto-insurance-uk')->first();
        $this->assertNotNull($campaign);

        $account = Account::find($campaign->account_id);
        $settings = $account->settings ?? [];
        $settings['validation_integration'] = [
            'enabled' => true,
            'provider' => 'ipqs',
            'email_validation' => false,
            'hlr_validation' => false,
            'ip_validation' => true,
            'url_validation' => false,
            'quarantine_on_fail' => true,
            'ipqs' => [
                'api_key' => encrypt('test-key'),
                'fraud_score_threshold' => 85,
                'block_vpn' => true,
            ],
        ];
        $settings['subscription_plan'] = 'growth';
        $settings['fraud_protection'] = ['enabled' => true, 'included' => true, 'usage_count' => 0, 'usage_period' => now()->format('Y-m')];
        $account->update(['settings' => $settings]);

        $campaign->update([
            'validation_config' => array_merge($campaign->validation_config ?? [], [
                'quarantine_on_validation_fail' => true,
                'ip_validation' => true,
            ]),
        ]);

        Http::fake([
            'www.ipqualityscore.com/api/json/ip/*' => Http::response([
                'success' => true,
                'fraud_score' => 95,
                'proxy' => true,
                'vpn' => false,
                'tor' => false,
            ]),
        ]);

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Pending,
            'ip_address' => '198.51.100.44',
            'field_data' => $this->validFieldData($campaign),
            'received_at' => now(),
        ]);

        app(LeadPipeline::class)->process($lead->fresh());

        $lead->refresh();
        $this->assertSame(LeadStatus::Quarantined, $lead->status);
        $this->assertFalse($lead->metadata['ip_validation']['passed'] ?? true);
        $this->assertSame('ipqs', $lead->metadata['ip_validation']['provider'] ?? null);
    }

    public function test_pipeline_runs_url_validation_when_enabled(): void
    {
        $campaign = Campaign::withoutGlobalScopes()->with('fields')->where('reference', 'auto-insurance-uk')->first();
        $this->assertNotNull($campaign);

        $account = Account::find($campaign->account_id);
        $settings = $account->settings ?? [];
        $settings['validation_integration'] = [
            'enabled' => true,
            'provider' => 'ipqs',
            'email_validation' => false,
            'hlr_validation' => false,
            'ip_validation' => false,
            'url_validation' => true,
            'quarantine_on_fail' => true,
            'ipqs' => [
                'api_key' => encrypt('test-key'),
                'block_malware_url' => true,
            ],
        ];
        $settings['subscription_plan'] = 'growth';
        $settings['fraud_protection'] = ['enabled' => true, 'included' => true, 'usage_count' => 0, 'usage_period' => now()->format('Y-m')];
        $account->update(['settings' => $settings]);

        $campaign->update([
            'validation_config' => array_merge($campaign->validation_config ?? [], [
                'quarantine_on_validation_fail' => true,
                'url_validation' => true,
            ]),
        ]);

        Http::fake([
            'www.ipqualityscore.com/api/json/url/*' => Http::response([
                'success' => true,
                'unsafe' => false,
                'risk_score' => 20,
                'malware' => true,
                'phishing' => false,
                'suspicious' => false,
                'parking' => false,
                'spamming' => false,
            ]),
        ]);

        $fields = $this->validFieldData($campaign);
        $fields['website'] = 'https://malware.example/path';

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Pending,
            'field_data' => $fields,
            'received_at' => now(),
        ]);

        app(LeadPipeline::class)->process($lead->fresh());

        $lead->refresh();
        $this->assertSame(LeadStatus::Quarantined, $lead->status);
        $this->assertFalse($lead->metadata['url_validation']['passed'] ?? true);
    }

    public function test_resolver_falls_back_to_demo_when_ipqs_key_missing(): void
    {
        config(['validation.ipqs.api_key' => null]);

        $account = Account::withoutGlobalScopes()->first();
        $settings = $account->settings ?? [];
        $settings['validation_integration'] = ['provider' => 'ipqs'];
        $account->update(['settings' => $settings]);

        $provider = app(ValidationProviderResolver::class)->forAccount($account->fresh());

        $this->assertInstanceOf(\App\Services\Validation\DemoValidationProvider::class, $provider);
    }

    /**
     * @return array<string, string>
     */
    protected function validFieldData(Campaign $campaign): array
    {
        $fields = [];
        foreach ($campaign->fields as $field) {
            $fields[$field->name] = match ($field->name) {
                'email' => 'valid@example.com',
                'phone1' => '07700900123',
                'zipcode' => 'SW1A 1AA',
                'vehicle_year', 'loan_amount', 'monthly_income' => '10000',
                default => 'Test',
            };
        }

        return $fields;
    }
}
