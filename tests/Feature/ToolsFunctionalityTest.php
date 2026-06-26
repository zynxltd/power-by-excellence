<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Postback;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Webhook;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ToolsFunctionalityTest extends TestCase
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

    protected function tenantRequest()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_tools_routes_map_to_tools_module(): void
    {
        $this->assertSame('tools', AdminModules::moduleForRoute('api-keys.index'));
        $this->assertSame('tools', AdminModules::moduleForRoute('integrations.index'));
        $this->assertSame('tools', AdminModules::moduleForRoute('integrations.validation'));
        $this->assertSame('tools', AdminModules::moduleForRoute('help.index'));
        $this->assertSame('tools', AdminModules::moduleForRoute('notifications.admin.index'));
    }

    public function test_integrations_hub_lists_connectors(): void
    {
        Webhook::create([
            'account_id' => $this->account->id,
            'name' => 'CRM Hook',
            'url' => 'https://example.com/hook',
            'events' => ['lead.sold'],
            'is_active' => true,
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Integrations/Index')
                ->has('integrations')
                ->has('stats')
                ->where('stats.connected', fn ($count) => $count >= 1)
            );
    }

    public function test_stripe_integration_settings_persist_and_mask_secrets(): void
    {
        $this->tenantRequest()
            ->actingAs($this->admin)
            ->put(route('integrations.stripe.update'), [
                'enabled' => true,
                'mode' => 'test',
                'publishable_key' => 'pk_test_123',
                'secret_key' => 'sk_test_secret',
                'webhook_secret' => 'whsec_test',
                'buyer_self_serve_topup' => true,
            ])
            ->assertRedirect();

        $stripe = $this->account->fresh()->settings['stripe'];
        $this->assertTrue($stripe['enabled']);
        $this->assertSame('pk_test_123', $stripe['publishable_key']);
        $this->assertNotSame('sk_test_secret', $stripe['secret_key']);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('integrations.stripe'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stripe.secret_key', '••••••••')
                ->where('stripe.publishable_key', 'pk_test_123')
            );

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->put(route('integrations.stripe.update'), [
                'enabled' => true,
                'mode' => 'test',
                'publishable_key' => 'pk_test_123',
                'secret_key' => '••••••••',
                'webhook_secret' => '••••••••',
                'buyer_self_serve_topup' => true,
            ])
            ->assertRedirect();

        $this->assertSame($stripe['secret_key'], $this->account->fresh()->settings['stripe']['secret_key']);
    }

    public function test_validation_integration_update_and_test(): void
    {
        $this->tenantRequest()
            ->actingAs($this->admin)
            ->put(route('integrations.validation.update'), [
                'enabled' => true,
                'email_validation' => true,
                'hlr_validation' => false,
                'quarantine_on_fail' => true,
            ])
            ->assertRedirect();

        $settings = $this->account->fresh()->settings['validation_integration'];
        $this->assertTrue($settings['email_validation']);
        $this->assertFalse($settings['hlr_validation']);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->post(route('integrations.validation.test'), [
                'email' => 'user@invalid.demo',
                'phone' => '07000123456',
            ])
            ->assertRedirect()
            ->assertSessionHas('testResults', fn ($results) => ($results['email']['passed'] ?? true) === false);
    }

    public function test_lead_source_integration_saves_campaign_mapping(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->put(route('integrations.lead-source.update', 'facebook'), [
                'enabled' => true,
                'verify_token' => 'fb-verify-token',
                'campaign_id' => $campaign->id,
                'field_mapping' => ['email' => 'email'],
            ])
            ->assertRedirect();

        $config = $this->account->fresh()->settings['lead_sources']['facebook'];
        $this->assertTrue($config['enabled']);
        $this->assertSame($campaign->id, $config['campaign_id']);
        $this->assertSame('fb-verify-token', $config['verify_token']);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('integrations.lead-source', 'facebook'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Integrations/LeadSource')
                ->where('provider', 'facebook')
                ->where('config.verify_token', 'fb-verify-token')
                ->has('webhookUrl')
                ->has('ingestUrl')
            );
    }

    public function test_lead_source_generates_verify_token_when_blank(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->put(route('integrations.lead-source.update', 'facebook'), [
                'enabled' => true,
                'verify_token' => '',
                'campaign_id' => $campaign->id,
            ])
            ->assertRedirect();

        $token = $this->account->fresh()->settings['lead_sources']['facebook']['verify_token'] ?? '';
        $this->assertNotSame('', $token);
        $this->assertGreaterThanOrEqual(16, strlen($token));
    }

    public function test_unknown_lead_source_provider_returns_404(): void
    {
        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('integrations.lead-source', 'snapchat'))
            ->assertNotFound();
    }

    public function test_api_keys_index_create_and_revoke(): void
    {
        $supplier = Supplier::where('account_id', $this->account->id)->first();

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('api-keys.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ApiKeys/Index')
                ->has('apiKeys')
                ->has('suppliers')
            );

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->post(route('api-keys.store'), [
                'name' => 'Supplier ingest key',
                'type' => 'supplier',
                'supplier_id' => $supplier->id,
                'permissions' => ['leads.create', 'leads.read'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $key = ApiKey::where('name', 'Supplier ingest key')->first();
        $this->assertNotNull($key);
        $this->assertSame($supplier->id, $key->supplier_id);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->delete(route('api-keys.destroy', $key))
            ->assertRedirect();

        $this->assertNull($key->fresh());
    }

    public function test_tenant_admin_cannot_revoke_other_tenant_api_key(): void
    {
        $otherAccount = Account::where('slug', 'insurance-ca')->first();
        $key = ApiKey::withoutGlobalScopes()->create([
            'account_id' => $otherAccount->id,
            'name' => 'Foreign key',
            'type' => 'administrator',
            'key_prefix' => 'foreign',
            'key_hash' => hash('sha256', 'foreign'),
            'permissions' => ['*'],
            'is_active' => true,
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->delete(route('api-keys.destroy', $key))
            ->assertNotFound();
    }

    public function test_webhook_store_validates_url(): void
    {
        $this->tenantRequest()
            ->actingAs($this->admin)
            ->post(route('webhooks.store'), [
                'name' => 'Bad URL',
                'url' => 'not-a-url',
                'events' => ['lead.sold'],
                'is_active' => true,
            ])
            ->assertSessionHasErrors('url');
    }

    public function test_postback_update_persists_changes(): void
    {
        $postback = Postback::create([
            'account_id' => $this->account->id,
            'name' => 'Original',
            'url' => 'https://tracker.test/pb',
            'method' => 'get',
            'events' => ['lead.sold'],
            'is_active' => true,
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->put(route('postbacks.update', $postback), [
                'name' => 'Updated pixel',
                'url' => 'https://tracker.test/updated',
                'method' => 'post',
                'events' => ['lead.sold', 'lead.accepted'],
                'is_active' => false,
            ])
            ->assertRedirect();

        $postback->refresh();
        $this->assertSame('Updated pixel', $postback->name);
        $this->assertSame('post', $postback->method);
        $this->assertFalse($postback->is_active);
    }

    public function test_lead_csv_import_creates_import_record(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $csv = "firstname,email,phone1,zipcode\nImport,import.".uniqid()."@example.com,07700900123,SW1A 1AA\n";
        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Imports/Index')
                ->has('imports')
                ->has('campaigns')
            );

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->post(route('imports.store'), [
                'campaign_id' => $campaign->id,
                'file' => $file,
            ])
            ->assertRedirect(route('imports.index'));

        $this->assertDatabaseHas('lead_imports', [
            'campaign_id' => $campaign->id,
            'filename' => 'leads.csv',
        ]);
    }

    public function test_features_hub_pages_load(): void
    {
        $routes = [
            'features.index',
            'features.capture',
            'features.validation',
            'features.routing',
            'features.delivery',
            'features.auto-responders',
        ];

        foreach ($routes as $route) {
            $this->tenantRequest()
                ->actingAs($this->admin)
                ->get(route($route))
                ->assertOk();
        }
    }
}
