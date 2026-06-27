<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Api\ApiKeyService;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\BuyerBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdgeCaseRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_portal_cross_access_is_blocked(): void
    {
        $host = ['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'];
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $buyer = User::where('email', 'buyer-portal@excellence-uk.test')->first();
        $supplier = User::where('email', 'supplier-portal@excellence-uk.test')->first();

        $h = fn () => $this->withServerVariables($host);

        $h()->actingAs($admin)->get('/portal/buyer')->assertForbidden();
        $h()->actingAs($admin)->get('/portal/supplier')->assertForbidden();
        $h()->actingAs($buyer)->get('/dashboard')->assertRedirect(route('portal.buyer.dashboard'));
        $h()->actingAs($buyer)->get('/portal/supplier')->assertRedirect(route('portal.buyer.dashboard'));
        $h()->actingAs($supplier)->get('/dashboard')->assertRedirect(route('portal.supplier.dashboard'));
        $h()->actingAs($supplier)->get('/portal/buyer')->assertRedirect(route('portal.supplier.dashboard'));
    }

    public function test_supplier_portal_role_guard(): void
    {
        $account = Account::create([
            'name' => 'Edge Tenant',
            'slug' => 'edge-tenant',
            'domain' => 'edge-tenant.powerbyexcellence.test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
        ]);
        $supplier = Supplier::create([
            'account_id' => $account->id,
            'reference' => 'edge-supplier',
            'name' => 'Edge Supplier',
            'status' => 'active',
        ]);
        $supplierUser = User::factory()->create([
            'account_id' => $account->id,
            'supplier_id' => $supplier->id,
            'role' => UserRole::SupplierPortal,
        ]);
        $adminUser = User::factory()->create([
            'account_id' => $account->id,
            'role' => UserRole::AccountAdmin,
        ]);

        $host = ['HTTP_HOST' => 'edge-tenant.powerbyexcellence.test'];

        $this->withServerVariables($host)->actingAs($supplierUser)->get('/portal/supplier')->assertOk();
        $this->withServerVariables($host)->actingAs($adminUser)->get('/portal/supplier')->assertForbidden();
    }

    public function test_portal_csv_downloads_return_csv(): void
    {
        $host = ['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'];
        $buyer = User::where('email', 'buyer-portal@excellence-uk.test')->first();
        $supplier = User::where('email', 'supplier-portal@excellence-uk.test')->first();

        $this->withServerVariables($host)->actingAs($buyer)
            ->get('/portal/buyer/leads/download')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->withServerVariables($host)->actingAs($supplier)
            ->get('/portal/supplier/leads/download')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_billing_lock_blocks_ingest_and_admin_routes(): void
    {
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $account = $admin->account;
        $campaign = Campaign::where('account_id', $account->id)->first();

        app(AccountBillingService::class)->lock($account, 'Edge case lock');

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect(route('billing.lock'));

        $key = app(ApiKeyService::class)->create([
            'account_id' => $account->id,
            'name' => 'Lock test key',
            'type' => 'administrator',
            'permissions' => ['leads.create'],
        ]);

        $this->postJson('/api/v1/leads', [
            'campaign_reference' => $campaign->reference,
            'sync' => true,
            'firstname' => 'Locked',
            'lastname' => 'Test',
            'email' => 'locked.'.uniqid().'@example.com',
            'phone1' => '07700900111',
            'zipcode' => 'SW1A 1AA',
        ], ['Authorization' => 'Bearer '.$key['token']])
            ->assertStatus(402);
    }

    public function test_zero_credit_buyer_cannot_be_charged_when_prepay_required(): void
    {
        $account = Account::where('slug', 'excellence-uk')->first();
        $account->update([
            'settings' => array_merge($account->settings ?? [], ['require_buyer_prepay' => true]),
        ]);

        $buyer = Buyer::where('account_id', $account->id)->first();
        $buyer->update(['credit_balance' => 0]);

        $billing = app(BuyerBillingService::class);
        $this->assertFalse($billing->hasCredit($buyer, 1));
        $this->assertFalse($billing->charge($buyer, 1));
    }

    public function test_supplier_api_key_rejects_wrong_campaign_tenant(): void
    {
        $uk = Account::where('slug', 'excellence-uk')->first();
        $ca = Account::where('slug', 'insurance-ca')->first();
        $supplier = Supplier::where('account_id', $uk->id)->first();
        $caCampaign = Campaign::where('account_id', $ca->id)->first();

        $key = app(ApiKeyService::class)->create([
            'account_id' => $uk->id,
            'supplier_id' => $supplier->id,
            'name' => 'UK supplier key',
            'type' => 'supplier',
            'permissions' => ['leads.create'],
        ]);

        $this->postJson('/api/v1/leads', [
            'campaign_reference' => $caCampaign->reference,
            'sync' => true,
            'firstname' => 'Cross',
            'lastname' => 'Tenant',
            'email' => 'cross.'.uniqid().'@example.com',
            'phone1' => '07700900222',
            'zipcode' => 'SW1A 1AA',
        ], ['Authorization' => 'Bearer '.$key['token']])
            ->assertStatus(404);
    }

    public function test_unauthenticated_and_guest_boundaries(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/portal/buyer')->assertRedirect('/login');
        $this->get('/portal/supplier')->assertRedirect('/login');
        $this->get('/help/nonexistent-article-slug-xyz')->assertNotFound();
        $this->get('/blog/nonexistent-blog-slug')->assertNotFound();
        $this->post(route('demo.request'), [])->assertSessionHasErrors(['name', 'email', 'company']);
    }

    public function test_invalid_api_requests_are_rejected(): void
    {
        $this->postJson('/api/v1/leads', [])->assertUnauthorized();

        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $key = app(ApiKeyService::class)->create([
            'account_id' => $admin->account_id,
            'name' => 'Invalid payload key',
            'type' => 'administrator',
            'permissions' => ['leads.create'],
        ]);

        $this->postJson('/api/v1/leads', [
            'campaign_reference' => 'nonexistent-campaign-ref',
        ], ['Authorization' => 'Bearer '.$key['token']])
            ->assertNotFound();
    }

    public function test_seed_data_integrity(): void
    {
        $this->assertSame(0, Lead::withoutGlobalScopes()->whereNull('supplier_id')->count());
        $this->assertSame(0, User::whereNotNull('supplier_id')->whereDoesntHave('supplier')->count());
        $this->assertSame(0, User::whereNotNull('buyer_id')->whereDoesntHave('buyer')->count());
    }

    public function test_supplier_embeds_show_tenant_forms_assigned_via_default_supplier(): void
    {
        $host = ['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'];
        $account = Account::where('slug', 'excellence-uk')->first();
        $supplier = Supplier::where('account_id', $account->id)->where('reference', 'supplier-main')->first();
        $campaign = Campaign::where('account_id', $account->id)->where('reference', 'solar-uk')->first();
        $supplierUser = User::where('email', 'supplier-portal@excellence-uk.test')->first();

        \App\Models\CampaignSupplier::where('supplier_id', $supplier->id)->delete();

        \App\Models\HostedForm::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'name' => 'Tenant assigned form',
            'slug' => 'tenant-assigned-form',
            'is_active' => true,
            'config' => [
                'multi_step' => true,
                'default_supplier_id' => $supplier->id,
                'steps' => [],
            ],
        ]);

        $this->withServerVariables($host)
            ->actingAs($supplierUser)
            ->get(route('portal.supplier.embeds'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('forms', fn ($forms) => collect($forms)->pluck('slug')->contains('tenant-assigned-form'))
            );
    }

    public function test_supplier_embeds_only_show_forms_for_assigned_campaigns(): void
    {
        $host = ['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'];
        $account = Account::where('slug', 'excellence-uk')->first();
        $supplier = Supplier::where('account_id', $account->id)->where('reference', 'supplier-main')->first();
        $assignedCampaign = Campaign::where('account_id', $account->id)->where('reference', 'mortgage-uk')->first();
        $otherCampaign = Campaign::where('account_id', $account->id)->where('reference', 'solar-uk')->first();
        $supplierUser = User::where('email', 'supplier-portal@excellence-uk.test')->first();

        \App\Models\CampaignSupplier::where('supplier_id', $supplier->id)->delete();

        \App\Models\HostedForm::create([
            'account_id' => $account->id,
            'campaign_id' => $assignedCampaign->id,
            'name' => 'Assigned Campaign Form',
            'slug' => 'assigned-campaign-form',
            'is_active' => true,
            'config' => ['multi_step' => true, 'steps' => []],
        ]);

        \App\Models\HostedForm::create([
            'account_id' => $account->id,
            'campaign_id' => $otherCampaign->id,
            'name' => 'Unassigned Campaign Form',
            'slug' => 'unassigned-campaign-form',
            'is_active' => true,
            'config' => ['multi_step' => true, 'steps' => []],
        ]);

        $this->withServerVariables($host)
            ->actingAs($supplierUser)
            ->get(route('portal.supplier.embeds'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('forms', 0));

        \App\Models\CampaignSupplier::create([
            'campaign_id' => $assignedCampaign->id,
            'supplier_id' => $supplier->id,
        ]);

        $this->withServerVariables($host)
            ->actingAs($supplierUser)
            ->get(route('portal.supplier.embeds'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('forms', fn ($forms) => collect($forms)->pluck('slug')->contains('assigned-campaign-form'))
                ->where('forms', fn ($forms) => ! collect($forms)->pluck('slug')->contains('unassigned-campaign-form'))
            );
    }

    public function test_error_flash_is_set_for_blocked_user_actions(): void
    {
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->post(route('users.suspend', $admin))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_whitelisted_ip_skips_ip_check_but_not_email(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'www.ipqualityscore.com/api/json/email/*' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'valid' => false,
                'fraud_score' => 99,
                'disposable' => false,
            ]),
            'www.ipqualityscore.com/api/json/ip/*' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'fraud_score' => 99,
                'proxy' => true,
            ]),
        ]);

        $provider = new \App\Services\Validation\IpqsValidationProvider(['api_key' => 'test-key']);
        $context = new \App\Services\Validation\ValidationContext(ipWhitelist: '198.51.100.10');

        $ipResult = $provider->validateIp('198.51.100.10', $context);
        $emailResult = $provider->validateEmail('bad@example.com', $context);

        $this->assertTrue($ipResult->passed);
        $this->assertFalse($emailResult->passed);
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_form_submit_ignores_cross_tenant_supplier_id(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $ukCampaign = Campaign::whereHas('account', fn ($q) => $q->where('slug', 'excellence-uk'))->first();
        $foreignSupplier = Supplier::whereHas('account', fn ($q) => $q->where('slug', 'insurance-ca'))->first();
        $email = 'scope.'.uniqid().'@example.com';

        $form = \App\Models\HostedForm::create([
            'account_id' => $ukCampaign->account_id,
            'campaign_id' => $ukCampaign->id,
            'name' => 'Supplier scope form',
            'slug' => 'supplier-scope-form',
            'is_active' => true,
            'config' => [
                'multi_step' => true,
                'steps' => [[
                    'id' => 'step-1',
                    'title' => 'Contact',
                    'fields' => [
                        ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'options' => []],
                    ],
                ]],
            ],
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->post(route('forms.submit', $form->slug), [
                'email' => $email,
                'supplier_id' => (string) $foreignSupplier->id,
            ], [
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk();

        $lead = Lead::where('campaign_id', $ukCampaign->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($lead);
        $this->assertSame($email, $lead->field_data['email'] ?? null);
        $this->assertNull($lead->supplier_id);
    }
}
