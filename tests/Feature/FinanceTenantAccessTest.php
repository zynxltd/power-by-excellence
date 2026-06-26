<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTenantAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_ca_admin_can_access_finance_on_ca_tenant(): void
    {
        $caAdmin = User::where('email', 'ca@powerbyexcellence.test')->first();

        $this->actingAs($caAdmin)
            ->get('http://insurance-ca.powerbyexcellence.test/finance')
            ->assertOk();
    }

    public function test_uk_admin_is_redirected_from_ca_tenant_finance(): void
    {
        $ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->actingAs($ukAdmin)
            ->get('http://insurance-ca.powerbyexcellence.test/finance')
            ->assertRedirect('http://excellence-uk.powerbyexcellence.test/finance')
            ->assertSessionHas('error');
    }

    public function test_super_admin_can_access_finance_on_ca_tenant(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->actingAs($super)
            ->get('http://insurance-ca.powerbyexcellence.test/finance')
            ->assertOk();
    }

    public function test_super_admin_with_god_mode_session_on_ca_tenant(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();
        $account = \App\Models\Account::where('slug', 'insurance-ca')->first();

        $this->actingAs($super)
            ->withSession(['god_mode' => true, 'current_account_id' => $account->id])
            ->get('http://insurance-ca.powerbyexcellence.test/finance')
            ->assertOk();
    }

    public function test_super_admin_on_central_without_tenant_redirects_to_accounts(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'powerbyexcellence.test'])
            ->actingAs($super)
            ->get(route('finance.index'))
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('error', 'Select a partner platform first.');
    }
}
