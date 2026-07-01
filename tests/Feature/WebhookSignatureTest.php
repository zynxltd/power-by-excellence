<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Lead;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Distribution\WebhookDispatcher;
use App\Services\Webhooks\WebhookSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
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
        $this->registerWebhookSigningRoutes();
    }

    protected function registerWebhookSigningRoutes(): void
    {
        require_once base_path('routes/compliance-phase-3.php');
        registerCompliancePhase3WebhookSigningRoutes();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_dispatched_webhook_includes_valid_signature(): void
    {
        Http::fake();

        $secret = 'whsec_test_signature_secret_32chars!!';
        Webhook::create([
            'account_id' => $this->account->id,
            'name' => 'Signed hook',
            'url' => 'https://crm.test/signed-hook',
            'events' => ['lead.sold'],
            'is_active' => true,
            'secret' => $secret,
            'config' => ['sign_payloads' => true],
        ]);

        $campaign = \App\Models\Campaign::where('account_id', $this->account->id)->first();
        $lead = Lead::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'signed@test.com'],
            'received_at' => now(),
        ]);

        app(WebhookDispatcher::class)->dispatch($this->account, 'lead.sold', $lead);

        $signatures = app(WebhookSignatureService::class);

        Http::assertSent(function ($request) use ($secret, $signatures) {
            if ($request->url() !== 'https://crm.test/signed-hook') {
                return false;
            }

            $body = $request->body();
            $header = $request->header('X-Signature')[0] ?? '';

            return $signatures->verify($secret, $body, $header);
        });
    }

    public function test_unsigned_webhook_omits_signature_header(): void
    {
        Http::fake();

        Webhook::create([
            'account_id' => $this->account->id,
            'name' => 'Plain hook',
            'url' => 'https://crm.test/plain-hook',
            'events' => ['lead.sold'],
            'is_active' => true,
            'config' => ['sign_payloads' => false],
        ]);

        $campaign = \App\Models\Campaign::where('account_id', $this->account->id)->first();
        $lead = Lead::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'plain@test.com'],
            'received_at' => now(),
        ]);

        app(WebhookDispatcher::class)->dispatch($this->account, 'lead.sold', $lead);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://crm.test/plain-hook'
                && empty($request->header('X-Signature'));
        });
    }

    public function test_invalid_secret_fails_signature_verification(): void
    {
        $signatures = app(WebhookSignatureService::class);
        $body = $signatures->encodePayload(['event' => 'lead.sold', 'lead_uuid' => 'abc-123']);
        $validHeader = $signatures->headerValue('correct-secret', $body);

        $this->assertTrue($signatures->verify('correct-secret', $body, $validHeader));
        $this->assertFalse($signatures->verify('wrong-secret', $body, $validHeader));
    }

    public function test_generate_signing_secret_endpoint_returns_secret(): void
    {
        $this->ukHost()
            ->actingAs($this->admin)
            ->postJson('/webhooks/generate-signing-secret')
            ->assertOk()
            ->assertJsonStructure(['secret'])
            ->assertJson(fn ($json) => $json->whereType('secret', 'string'));
    }
}
