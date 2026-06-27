<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function centralHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'powerbyexcellence.test']);
    }

    protected function emeaHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'emea-loans.powerbyexcellence.test']);
    }

    public function test_authenticated_supplier_on_central_is_redirected_to_supplier_portal(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $account = Account::where('slug', 'emea-loans')->firstOrFail();
        $supplier = Supplier::where('account_id', $account->id)->firstOrFail();
        $user = User::where('email', 'supplier-portal@emea-loans.test')->firstOrFail();

        $this->centralHost()
            ->actingAs($user)
            ->get(route('platform.entry'))
            ->assertRedirect(TenantResolver::portalUrl($account, '/portal/supplier'));
    }

    public function test_authenticated_super_admin_on_central_goes_to_dashboard(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $super = User::where('email', 'admin@powerbyexcellence.test')->firstOrFail();

        $this->centralHost()
            ->actingAs($super)
            ->get(route('platform.entry'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_marketing_sign_in_url_on_central_points_to_platform_entry_when_authenticated(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $user = User::where('email', 'supplier-portal@emea-loans.test')->firstOrFail();

        $this->centralHost()
            ->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('urls.marketingSignIn', route('platform.entry'))
            );
    }

    public function test_guest_cannot_access_platform_entry(): void
    {
        $this->centralHost()
            ->get(route('platform.entry'))
            ->assertRedirect(route('login'));
    }

    public function test_supplier_already_on_tenant_host_uses_same_host_redirect(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $account = Account::where('slug', 'emea-loans')->firstOrFail();
        $user = User::where('email', 'supplier-portal@emea-loans.test')->firstOrFail();

        $this->emeaHost()
            ->actingAs($user)
            ->get(route('platform.entry'))
            ->assertRedirect(TenantResolver::portalUrl($account, '/portal/supplier'));
    }
}
