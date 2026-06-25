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
        $h()->actingAs($buyer)->get('/dashboard')->assertForbidden();
        $h()->actingAs($buyer)->get('/portal/supplier')->assertForbidden();
        $h()->actingAs($supplier)->get('/dashboard')->assertForbidden();
        $h()->actingAs($supplier)->get('/portal/buyer')->assertForbidden();
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
}
