<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Webhook;
use App\Support\Tenancy\AccountContext;
use App\Support\Tenancy\TenantResolver;
use App\Services\Platform\TenantHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $ukAdmin;

    protected Account $ukAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->ukAccount = Account::where('slug', 'excellence-uk')->first();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_central_host_does_not_resolve_tenant(): void
    {
        $this->assertNull(TenantResolver::resolveFromHost('powerbyexcellence.test'));
        $this->assertTrue(TenantResolver::isCentralHost('powerbyexcellence.test'));
    }

    public function test_custom_domain_resolves_to_account_slug(): void
    {
        $account = TenantResolver::resolveFromHost('solar-us.powerbyexcellence.test');

        $this->assertNotNull($account);
        $this->assertSame('partner-solar-us', $account->slug);
        $this->assertSame('solar-us.powerbyexcellence.test', $account->domain);
    }

    public function test_inactive_account_does_not_resolve_from_host(): void
    {
        $account = Account::create([
            'name' => 'Inactive Tenant',
            'slug' => 'inactive-tenant',
            'domain' => 'inactive.powerbyexcellence.test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => false,
        ]);

        $this->assertFalse($account->is_active);
        $this->assertNull(TenantResolver::resolveFromHost('inactive.powerbyexcellence.test'));
    }

    public function test_account_context_scopes_and_autofills_models(): void
    {
        $account = Account::create([
            'name' => 'Scope Test',
            'slug' => 'scope-test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
        ]);

        AccountContext::set($account);

        $buyer = Buyer::create([
            'reference' => 'scope-buyer',
            'name' => 'Scope Buyer',
            'status' => 'active',
        ]);

        $this->assertSame($account->id, $buyer->account_id);
        $this->assertCount(1, Buyer::all());
        $this->assertSame('Scope Buyer', Buyer::first()->name);

        AccountContext::clear();
        $this->assertCount(1, Buyer::withoutGlobalScopes()->where('account_id', $account->id)->get());
    }

    public function test_uk_admin_cannot_view_other_tenant_buyer(): void
    {
        $usBuyer = Buyer::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'partner-solar-us'))
            ->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('buyers.show', $usBuyer))
            ->assertNotFound();
    }

    public function test_uk_admin_cannot_view_other_tenant_supplier(): void
    {
        $caSupplier = Supplier::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'insurance-ca'))
            ->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('suppliers.show', $caSupplier))
            ->assertNotFound();
    }

    public function test_uk_admin_cannot_delete_other_tenant_webhook(): void
    {
        $usAccount = Account::where('slug', 'partner-solar-us')->first();
        $webhook = Webhook::withoutGlobalScopes()->create([
            'account_id' => $usAccount->id,
            'name' => 'US outbound',
            'url' => 'https://example.com/hook',
            'events' => ['lead.sold'],
            'is_active' => true,
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->delete(route('webhooks.destroy', $webhook))
            ->assertNotFound();

        $this->assertDatabaseHas('webhooks', ['id' => $webhook->id]);
    }

    public function test_uk_admin_cannot_suspend_other_tenant_user(): void
    {
        $usAdmin = User::where('email', 'us@powerbyexcellence.test')->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('users.suspend', $usAdmin))
            ->assertNotFound();

        $this->assertFalse($usAdmin->fresh()->is_suspended);
    }

    public function test_uk_admin_cannot_assign_other_tenant_buyer_to_new_user(): void
    {
        $usBuyer = Buyer::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'partner-solar-us'))
            ->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('users.store'), [
                'name' => 'Cross Tenant Portal',
                'email' => 'cross-portal@test.test',
                'password' => 'Password123!',
                'role' => 'buyer_portal',
                'buyer_id' => $usBuyer->id,
            ])
            ->assertSessionHasErrors('buyer_id');

        $this->assertDatabaseMissing('users', ['email' => 'cross-portal@test.test']);
    }

    public function test_uk_admin_redirected_from_us_tenant_host(): void
    {
        $this->actingAs($this->ukAdmin)
            ->get('http://solar-us.powerbyexcellence.test/dashboard')
            ->assertRedirect('http://excellence-uk.powerbyexcellence.test/dashboard')
            ->assertSessionHas('error');
    }

    public function test_inertia_shared_tenant_matches_partner_host(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get('http://excellence-uk.powerbyexcellence.test/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('tenant')
                ->where('tenant.id', $this->ukAccount->id)
                ->where('tenant.slug', 'excellence-uk')
                ->where('auth.account.id', $this->ukAccount->id)
                ->where('tenantHub.id', $this->ukAccount->id)
                ->where('tenantHub.is_active', true)
            );
    }

    public function test_buyers_index_excludes_other_tenant_names(): void
    {
        $response = $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('buyers.index'))
            ->assertOk();

        $names = collect($response->viewData('page')['props']['buyers']['data'] ?? [])
            ->pluck('name')
            ->implode(' ');

        $this->assertStringNotContainsString('State Farm', $names);
        $this->assertStringContainsString('Aviva', $names);
    }

    public function test_tenant_health_idle_without_today_activity(): void
    {
        $account = Account::create([
            'name' => 'Idle Health',
            'slug' => 'idle-health',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
        ]);

        $this->assertSame('idle', app(TenantHealth::class)->status($account->id));
    }

    public function test_tenant_health_critical_when_post_success_rate_collapses(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $delivery = Delivery::where('campaign_id', $campaign->id)->first();

        for ($i = 0; $i < 10; $i++) {
            $lead = Lead::create([
                'account_id' => $this->ukAccount->id,
                'campaign_id' => $campaign->id,
                'status' => 'unsold',
                'field_data' => ['email' => "health-fail-{$i}@test.test"],
                'received_at' => now(),
            ]);

            DeliveryLog::create([
                'lead_id' => $lead->id,
                'delivery_id' => $delivery->id,
                'status' => 'failed',
                'ping_request' => ['tier' => 1],
                'post_request' => ['tier' => 1],
            ]);
        }

        $status = app(TenantHealth::class)->status($this->ukAccount->id);

        $this->assertContains($status, ['warning', 'critical']);
        $this->assertNotSame('healthy', $status);
    }

    public function test_portal_url_uses_custom_domain_when_set(): void
    {
        $us = Account::where('slug', 'partner-solar-us')->first();

        $this->assertStringContainsString(
            'solar-us.powerbyexcellence.test',
            $us->portalUrl('/login')
        );
    }

    public function test_slug_subdomain_portal_url_when_no_custom_domain(): void
    {
        $this->assertStringContainsString(
            'excellence-uk.powerbyexcellence.test',
            $this->ukAccount->portalUrl('/dashboard')
        );
    }
}
