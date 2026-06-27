<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantBillingFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected Account $ukAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->superAdmin = User::where('email', 'admin@powerbyexcellence.test')->first();
        $this->ukAccount = Account::where('slug', 'excellence-uk')->first();
    }

    protected function centralHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'powerbyexcellence.test']);
    }

    public function test_super_admin_can_view_tenant_billing_index_on_central_host(): void
    {
        $this->centralHost()
            ->actingAs($this->superAdmin)
            ->get(route('accounts.billing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounts/Billing/Index')
                ->has('accounts')
                ->where('accounts', fn ($accounts) => collect($accounts)->pluck('slug')->contains('excellence-uk'))
            );
    }

    public function test_tenant_admin_cannot_access_tenant_billing(): void
    {
        $tenantAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->centralHost()
            ->actingAs($tenantAdmin)
            ->get(route('accounts.billing.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_update_tenant_contract_billing(): void
    {
        $this->centralHost()
            ->actingAs($this->superAdmin)
            ->put(route('accounts.billing.update', $this->ukAccount), [
                'monthly_rent' => 799,
                'contract_reference' => 'MSA-2026-UK',
                'billing_status' => 'active',
                'billing_notes' => 'Signed annual contract',
                'billing_alert_emails' => 'billing@powerbyexcellence.test',
                'subscription_plan' => 'growth',
            ])
            ->assertRedirect(route('accounts.billing.edit', $this->ukAccount));

        $settings = $this->ukAccount->fresh()->settings;
        $this->assertSame(799, $settings['monthly_rent']);
        $this->assertSame('MSA-2026-UK', $settings['contract_reference']);
        $this->assertSame('Signed annual contract', $settings['billing_notes']);
    }

    public function test_super_admin_can_lock_and_unlock_tenant_platform(): void
    {
        $this->centralHost()
            ->actingAs($this->superAdmin)
            ->post(route('accounts.billing.lock', $this->ukAccount), [
                'reason' => 'Rent overdue',
            ])
            ->assertRedirect();

        $this->assertSame(
            AccountBillingService::STATUS_LOCKED,
            app(AccountBillingService::class)->resolveStatus($this->ukAccount->fresh())
        );

        $this->centralHost()
            ->actingAs($this->superAdmin)
            ->post(route('accounts.billing.unlock', $this->ukAccount))
            ->assertRedirect();

        $this->assertSame(
            AccountBillingService::STATUS_ACTIVE,
            app(AccountBillingService::class)->resolveStatus($this->ukAccount->fresh())
        );
    }

    public function test_past_due_warns_but_does_not_block_processing(): void
    {
        $billing = app(AccountBillingService::class);

        $this->centralHost()
            ->actingAs($this->superAdmin)
            ->put(route('accounts.billing.update', $this->ukAccount), [
                'monthly_rent' => 799,
                'contract_reference' => 'MSA-TEST',
                'billing_status' => 'past_due',
                'billing_notes' => '',
                'billing_alert_emails' => '',
                'subscription_plan' => 'growth',
            ])
            ->assertRedirect();

        $account = $this->ukAccount->fresh();
        $this->assertSame(AccountBillingService::STATUS_PAST_DUE, $billing->resolveStatus($account));
        $this->assertTrue($billing->canAcceptLeads($account));
        $this->assertTrue($billing->canProcessLeads($account));
        $this->assertTrue($account->is_active);
    }

    public function test_locked_tenant_admin_redirected_to_billing_lock_page(): void
    {
        app(AccountBillingService::class)->lock($this->ukAccount, 'Rent overdue');

        $tenantAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($tenantAdmin)
            ->get(route('dashboard'))
            ->assertRedirect(route('billing.lock'));
    }
}
