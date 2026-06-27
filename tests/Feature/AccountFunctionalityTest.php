<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\User;
use App\Support\AdminModules;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $tenantAdmin;

    protected User $superAdmin;

    protected Account $ukAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->tenantAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->superAdmin = User::where('email', 'admin@powerbyexcellence.test')->first();
        $this->ukAccount = Account::where('slug', 'excellence-uk')->first();
    }

    protected function tenantRequest()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_accounts_routes_map_to_tenant_module(): void
    {
        $this->assertSame('tenant', AdminModules::moduleForRoute('accounts.index'));
        $this->assertSame('tenant', AdminModules::moduleForRoute('accounts.create'));
        $this->assertSame('tenant', AdminModules::moduleForRoute('accounts.store'));
        $this->assertSame('tenant', AdminModules::moduleForRoute('accounts.switch'));
    }

    public function test_super_admin_accounts_index_lists_platforms_with_counts(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounts/Index')
                ->has('accounts', fn (Assert $accounts) => $accounts
                    ->each(fn (Assert $account) => $account
                        ->has('id')
                        ->has('name')
                        ->has('slug')
                        ->has('domain')
                        ->has('portal_url')
                        ->has('campaigns_count')
                        ->has('leads_count')
                        ->has('buyers_count')
                        ->has('suppliers_count')
                        ->has('admin_user')
                    )
                )
                ->where('accounts', fn ($accounts) => count($accounts) >= 10)
            );
    }

    public function test_partner_platforms_only_on_central_host(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('accounts.index'))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get('http://excellence-uk.powerbyexcellence.test/accounts')
            ->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->post('http://excellence-uk.powerbyexcellence.test/accounts/switch', ['account_id' => $this->ukAccount->id])
            ->assertForbidden();
    }

    public function test_tenant_settings_page_loads_on_partner_domain(): void
    {
        $this->tenantRequest()
            ->actingAs($this->tenantAdmin)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/Edit')
                ->has('account')
                ->has('timezones')
                ->has('currencies')
                ->has('countries')
                ->where('account.id', $this->ukAccount->id)
            );
    }

    public function test_tenant_admin_can_update_platform_settings(): void
    {
        $this->tenantRequest()
            ->actingAs($this->tenantAdmin)
            ->put(route('settings.update'), [
                'name' => 'Excellence UK Updated',
                'timezone' => 'Europe/London',
                'default_country' => 'GB',
                'default_currency' => 'GBP',
                'require_buyer_prepay' => true,
                'billing_alert_emails' => 'billing@excellence.test',
                'default_low_credit_alert' => 250,
            ])
            ->assertRedirect();

        $account = $this->ukAccount->fresh();
        $this->assertSame('Excellence UK Updated', $account->name);
        $this->assertTrue($account->settings['require_buyer_prepay']);
        $this->assertSame('billing@excellence.test', $account->settings['billing_alert_emails']);
        $this->assertSame(250, $account->settings['default_low_credit_alert']);
    }

    public function test_settings_update_validates_country_and_currency_codes(): void
    {
        $this->tenantRequest()
            ->actingAs($this->tenantAdmin)
            ->put(route('settings.update'), [
                'name' => 'Bad codes',
                'timezone' => 'Europe/London',
                'default_country' => 'uk',
                'default_currency' => 'pound',
            ])
            ->assertSessionHasErrors(['default_country', 'default_currency']);
    }

    public function test_super_admin_without_tenant_context_is_redirected_from_settings(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('settings.edit'))
            ->assertRedirect(route('accounts.index'));
    }

    public function test_super_admin_can_update_switched_tenant_settings(): void
    {
        $us = Account::where('slug', 'partner-solar-us')->first();

        $this->actingAs($this->superAdmin)
            ->withSession(['current_account_id' => $us->id])
            ->put(route('settings.update'), [
                'name' => 'Solar US Admin Label',
                'timezone' => 'America/Chicago',
                'default_country' => 'US',
                'default_currency' => 'USD',
            ])
            ->assertRedirect();

        $this->assertSame('Solar US Admin Label', $us->fresh()->name);
        $this->assertSame('America/Chicago', $us->fresh()->timezone);
    }

    public function test_settings_billing_lock_deactivates_account(): void
    {
        $this->actingAs($this->superAdmin)
            ->withServerVariables(['HTTP_HOST' => 'powerbyexcellence.test'])
            ->put(route('accounts.billing.update', $this->ukAccount), [
                'monthly_rent' => 799,
                'contract_reference' => 'MSA-TEST',
                'billing_status' => 'locked',
                'billing_lock_reason' => 'Rent overdue',
                'billing_notes' => '',
                'billing_alert_emails' => '',
                'subscription_plan' => 'growth',
            ])
            ->assertRedirect(route('accounts.billing.edit', $this->ukAccount));

        $account = $this->ukAccount->fresh();
        $this->assertSame('locked', $account->settings['billing_status']);
        $this->assertFalse($account->is_active);
        $this->assertNotEmpty($account->settings['billing_locked_at']);
    }

    public function test_super_admin_clear_from_tenant_returns_to_central(): void
    {
        $response = $this->tenantRequest()
            ->actingAs($this->superAdmin)
            ->withSession(['current_account_id' => $this->ukAccount->id, 'god_mode' => true])
            ->post(route('accounts.clear'));

        $response->assertRedirect();
        $this->assertStringContainsString(
            TenantResolver::centralHosts()[0],
            $response->headers->get('Location') ?? ''
        );
        $response->assertSessionMissing('current_account_id');
        $response->assertSessionMissing('god_mode');
    }

    public function test_account_portal_url_and_resolved_domain(): void
    {
        $this->assertSame(
            'excellence-uk.powerbyexcellence.test',
            $this->ukAccount->resolvedDomain()
        );

        $this->assertStringContainsString(
            'excellence-uk.powerbyexcellence.test/dashboard',
            $this->ukAccount->portalUrl('/dashboard')
        );
    }

    public function test_super_admin_visit_from_central_redirects_to_tenant_handoff(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post('/accounts/'.$this->ukAccount->id.'/visit');

        $response->assertRedirect()
            ->assertSessionHas('god_mode', true)
            ->assertSessionHas('current_account_id', $this->ukAccount->id);

        $this->assertStringContainsString(
            'excellence-uk.powerbyexcellence.test/god-mode/handoff/',
            $response->headers->get('Location') ?? ''
        );
    }

    public function test_super_admin_can_open_create_platform_form(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('accounts.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounts/Create')
                ->has('baseDomain')
                ->has('timezones')
                ->has('currencies')
                ->has('countries')
            );
    }

    public function test_create_platform_form_only_on_central_host(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('http://excellence-uk.powerbyexcellence.test/accounts/create')
            ->assertForbidden();
    }

    public function test_tenant_admin_cannot_create_platform(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->get(route('accounts.create'))
            ->assertForbidden();

        $this->actingAs($this->tenantAdmin)
            ->post(route('accounts.store'), $this->validPlatformPayload())
            ->assertForbidden();
    }

    public function test_super_admin_can_provision_new_platform(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('accounts.store'), $this->validPlatformPayload())
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('success');

        $account = Account::where('slug', 'acme-leads')->first();
        $this->assertNotNull($account);
        $this->assertSame('Acme Leads', $account->name);
        $this->assertSame('GBP', $account->default_currency);
        $this->assertTrue($account->is_active);
        $this->assertTrue($account->settings['validation_integration']['enabled']);

        $admin = User::where('email', 'admin@acme-leads.test')->first();
        $this->assertNotNull($admin);
        $this->assertSame($account->id, $admin->account_id);
        $this->assertSame(UserRole::AccountAdmin, $admin->role);
        $this->assertSame('acme-leads.powerbyexcellence.test', $account->resolvedDomain());
    }

    public function test_create_platform_validates_unique_slug_and_admin_email(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('accounts.store'), array_merge($this->validPlatformPayload(), [
                'slug' => 'excellence-uk',
                'admin_email' => 'uk@powerbyexcellence.test',
            ]))
            ->assertSessionHasErrors(['slug', 'admin_email']);
    }

    public function test_create_platform_rejects_reserved_slug(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('accounts.store'), array_merge($this->validPlatformPayload(), [
                'slug' => 'api',
            ]))
            ->assertSessionHasErrors(['slug']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validPlatformPayload(): array
    {
        return [
            'name' => 'Acme Leads',
            'slug' => 'acme-leads',
            'domain' => '',
            'timezone' => 'Europe/London',
            'default_country' => 'GB',
            'default_currency' => 'GBP',
            'admin_name' => 'Acme Admin',
            'admin_email' => 'admin@acme-leads.test',
            'admin_password' => 'SecurePass1!',
            'send_credentials' => false,
        ];
    }
}
