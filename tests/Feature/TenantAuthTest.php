<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\User;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_tenant_resolves_from_domain(): void
    {
        $account = TenantResolver::resolveFromHost('solar-us.powerbyexcellence.test');

        $this->assertNotNull($account);
        $this->assertSame('partner-solar-us', $account->slug);
    }

    public function test_account_admin_cannot_login_on_central_host(): void
    {
        $user = User::where('email', 'us@powerbyexcellence.test')->first();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_account_admin_can_login_on_tenant_host(): void
    {
        $user = User::where('email', 'us@powerbyexcellence.test')->first();

        $response = $this->post('http://solar-us.powerbyexcellence.test/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_super_admin_can_login_on_central_host(): void
    {
        $user = User::where('email', 'admin@powerbyexcellence.test')->first();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_buyer_portal_user_login_on_wrong_tenant_is_rejected(): void
    {
        $buyerUser = User::where('email', 'buyer-portal@excellence-uk.test')->first();

        $response = $this->withServerVariables(['HTTP_HOST' => 'solar-us.powerbyexcellence.test'])
            ->post('http://solar-us.powerbyexcellence.test/login', [
                'email' => $buyerUser->email,
                'password' => 'password',
            ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_super_admin_cannot_login_on_tenant_host(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->post('http://excellence-uk.powerbyexcellence.test/login', [
                'email' => $super->email,
                'password' => 'password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_tenant_host_redirects_marketing_home_to_login(): void
    {
        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->get('http://excellence-uk.powerbyexcellence.test/')
            ->assertRedirect('/login');
    }

    public function test_tenant_host_redirects_pricing_to_login(): void
    {
        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->get('http://excellence-uk.powerbyexcellence.test/pricing')
            ->assertRedirect('/login');
    }

    public function test_logout_clears_impersonation_and_tenant_context(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();
        $ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->actingAs($ukAdmin)
            ->withSession(['impersonator_id' => $super->id, 'current_account_id' => 1])
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_supplier_portal_user_can_login_on_tenant_host(): void
    {
        $supplier = User::where('email', 'supplier-portal@excellence-uk.test')->first();

        $response = $this->post('http://excellence-uk.powerbyexcellence.test/login', [
            'email' => $supplier->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('portal.supplier.dashboard', absolute: false));
    }

    public function test_buyer_portal_user_can_login_on_tenant_host(): void
    {
        $buyer = User::where('email', 'buyer-portal@excellence-uk.test')->first();

        $response = $this->post('http://excellence-uk.powerbyexcellence.test/login', [
            'email' => $buyer->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('portal.buyer.dashboard', absolute: false));
    }

    public function test_super_admin_impersonates_tenant_admin_via_handoff(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();
        $ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $response = $this->actingAs($super)
            ->post(route('impersonate.start', $ukAdmin));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('excellence-uk.powerbyexcellence.test/impersonate/handoff/', $location);

        $token = basename(parse_url($location, PHP_URL_PATH));

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->get("/impersonate/handoff/{$token}")
            ->assertRedirect('/dashboard')
            ->assertSessionHas('impersonator_id', $super->id);

        $this->assertAuthenticatedAs($ukAdmin);
    }

    public function test_admin_can_impersonate_buyer_portal_user(): void
    {
        $this->withoutVite();

        $admin = User::where('email', 'us@powerbyexcellence.test')->first();
        $buyerPortal = User::where('email', 'buyer-portal@partner-solar-us.test')->first();

        $response = $this->withServerVariables(['HTTP_HOST' => 'solar-us.powerbyexcellence.test'])
            ->actingAs($admin)
            ->post('http://solar-us.powerbyexcellence.test/impersonate/'.$buyerPortal->id);

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        if (str_contains($location, '/impersonate/handoff/')) {
            $token = basename(parse_url($location, PHP_URL_PATH));
            $this->withServerVariables(['HTTP_HOST' => 'solar-us.powerbyexcellence.test'])
                ->get("http://solar-us.powerbyexcellence.test/impersonate/handoff/{$token}")
                ->assertRedirect('/portal/buyer');
        } else {
            $response->assertRedirect('http://solar-us.powerbyexcellence.test/portal/buyer');
        }

        $this->assertAuthenticatedAs($buyerPortal);
        $this->assertSame($admin->id, session('impersonator_id'));
    }

    public function test_super_admin_can_end_impersonation_from_tenant_host(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();
        $ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
        $this->actingAs($ukAdmin);
        $this->withSession(['impersonator_id' => $super->id]);

        $response = $this->post('http://excellence-uk.powerbyexcellence.test/impersonate/stop');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('powerbyexcellence.test/impersonate/stop-handoff/', $location);

        $token = basename(parse_url($location, PHP_URL_PATH));

        $this->withServerVariables(['HTTP_HOST' => 'powerbyexcellence.test'])
            ->get("http://powerbyexcellence.test/impersonate/stop-handoff/{$token}")
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('impersonator_id');

        $this->assertAuthenticatedAs($super);
    }
}
