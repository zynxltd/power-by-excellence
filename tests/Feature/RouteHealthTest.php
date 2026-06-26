<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_public_routes(): void
    {
        $this->withoutVite();
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertNotFound();
    }

    public function test_admin_routes_require_auth(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/campaigns')->assertRedirect('/login');
    }

    public function test_admin_can_access_dashboard(): void
    {
        $this->withoutVite();
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $host = $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);

        $host->actingAs($admin)->get('/dashboard')->assertOk();
        $host->actingAs($admin)->get('/campaigns')->assertOk();
        $host->actingAs($admin)->get('/users')->assertOk();
        $host->actingAs($admin)->get('/branding')->assertOk();
        $host->actingAs($admin)->get('/settings')->assertOk();
        $host->actingAs($admin)->get('/operations')->assertOk();
        $host->actingAs($admin)->get('/billing')->assertOk();
        $host->actingAs($admin)->get('/distribution')->assertOk();
    }

    public function test_buyer_portal_access(): void
    {
        $this->withoutVite();
        $buyer = User::where('email', 'buyer-portal@excellence-uk.test')->first();
        $host = $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);

        $host->actingAs($buyer)->get('/portal/buyer')->assertOk();
        $host->actingAs($buyer)->get('/portal/buyer/billing')->assertOk();
        $host->actingAs($buyer)->get('/dashboard')->assertRedirect(route('portal.buyer.dashboard'));
    }

    public function test_super_admin_routes(): void
    {
        $this->withoutVite();
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();
        $this->actingAs($super)->get('/accounts')->assertOk();
        $this->actingAs($super)->get('/command-center')->assertOk();
        $this->actingAs($super)->get('/logs/changes')->assertOk();
        $this->actingAs($super)->get('/logs/security')->assertOk();
    }

    public function test_login_redirects_by_role(): void
    {
        $buyer = User::where('email', 'buyer-portal@excellence-uk.test')->first();
        $response = $this->post('http://excellence-uk.powerbyexcellence.test/login', [
                'email' => $buyer->email,
                'password' => 'password',
            ]);
        $response->assertRedirect(route('portal.buyer.dashboard'));
    }
}
