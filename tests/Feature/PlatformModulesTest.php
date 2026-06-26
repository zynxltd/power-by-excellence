<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke-test major modules aligned with LEADBYTE_REPLICA_DEV_DOC.md TOC §1–20.
 */
class PlatformModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_public_marketing_and_auth(): void
    {
        $this->get('/')->assertOk();
        $this->get('/pricing')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertNotFound();
        $this->get('/status')->assertOk();
        $this->get('/status.json')->assertOk();
        $this->get('/help')->assertOk();
    }

    public function test_admin_modules_uk_platform(): void
    {
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $routes = [
            '/dashboard',
            '/operations',
            '/logs/access',
            '/logs/changes',
            '/features',
            '/reports',
            '/routing/simulator',
            '/campaigns',
            '/distribution',
            '/deliveries',
            '/deliveries/create',
            '/integrations',
            '/buyers',
            '/suppliers',
            '/leads',
            '/billing',
            '/finance',
            '/imports',
            '/webhooks',
            '/postbacks',
            '/api-keys',
            '/automation',
            '/quarantine',
            '/integrations/validation',
            '/logs/api',
            '/logs/delivery',
            '/users',
            '/settings',
            '/branding',
            '/profile',
        ];

        foreach ($routes as $url) {
            $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
                ->actingAs($admin)
                ->get($url)
                ->assertOk();
        }
    }

    public function test_super_admin_platforms(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();
        $this->actingAs($super)->get('/accounts')->assertOk();
    }

    public function test_buyer_portal_modules(): void
    {
        $buyer = User::where('email', 'buyer-portal@excellence-uk.test')->first();

        foreach (['/portal/buyer', '/portal/buyer/leads', '/portal/buyer/billing', '/profile'] as $url) {
            $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
                ->actingAs($buyer)
                ->get($url)
                ->assertOk();
        }
    }

    public function test_supplier_portal_modules(): void
    {
        $supplier = User::where('email', 'supplier-portal@excellence-uk.test')->first();

        foreach (['/portal/supplier', '/portal/supplier/leads', '/portal/supplier/embeds', '/portal/supplier/billing', '/profile'] as $url) {
            $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
                ->actingAs($supplier)
                ->get($url)
                ->assertOk();
        }
    }

    public function test_us_platform_admin_multi_vertical(): void
    {
        $usAdmin = User::where('email', 'us@powerbyexcellence.test')->first();
        $host = $this->withServerVariables(['HTTP_HOST' => 'solar-us.powerbyexcellence.test']);
        $host->actingAs($usAdmin)->get('/dashboard')->assertOk();
        $host->actingAs($usAdmin)->get('/campaigns')->assertOk();

        foreach (['auto-insurance-us', 'loans-us', 'mortgage-us', 'payday-loans-us', 'solar-us'] as $ref) {
            $this->assertDatabaseHas('campaigns', ['reference' => $ref]);
        }
    }

    public function test_seeded_demo_data_sense_check(): void
    {
        $this->assertDatabaseCount('accounts', count(config('tenant_platforms', [])));
        $this->assertDatabaseHas('campaigns', ['reference' => 'auto-insurance-uk']);
        $this->assertDatabaseHas('campaigns', ['reference' => 'loans-uk']);
        $this->assertDatabaseHas('campaigns', ['reference' => 'mortgage-uk']);
        $this->assertDatabaseHas('campaigns', ['reference' => 'payday-loans-uk']);
        $this->assertDatabaseHas('campaigns', ['reference' => 'solar-uk']);
        $this->assertDatabaseHas('campaigns', ['reference' => 'solar-us']);
        $this->assertDatabaseHas('campaigns', ['reference' => 'auto-insurance-us']);
        $this->assertDatabaseHas('campaigns', ['reference' => 'loans-us']);
        $this->assertDatabaseHas('buyers', ['reference' => 'buyer-primary']);
        $this->assertDatabaseHas('buyers', ['reference' => 'buyer-secondary']);
        $this->assertGreaterThanOrEqual(4, \App\Models\Delivery::count());
        $this->assertGreaterThanOrEqual(5, \App\Models\Lead::withoutGlobalScopes()->where('status', 'sold')->count());
        $this->assertDatabaseHas('buyer_transactions', ['type' => 'credit', 'description' => 'Demo seed top-up']);
        $this->assertDatabaseHas('distribution_configs', ['name' => 'Hybrid Ping Tree']);
    }
}
