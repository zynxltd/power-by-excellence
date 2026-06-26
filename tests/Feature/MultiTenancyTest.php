<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadFinancial;
use App\Models\User;
use App\Support\Tenancy\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_scoped_queries(): void
    {
        $accountA = Account::create(['name' => 'A', 'slug' => 'a', 'default_currency' => 'GBP', 'default_country' => 'GB']);
        $accountB = Account::create(['name' => 'B', 'slug' => 'b', 'default_currency' => 'GBP', 'default_country' => 'GB']);

        Campaign::create(['account_id' => $accountA->id, 'name' => 'Camp A', 'reference' => 'a']);
        Campaign::create(['account_id' => $accountB->id, 'name' => 'Camp B', 'reference' => 'b']);

        AccountContext::set($accountA);

        $this->assertCount(1, Campaign::all());
        $this->assertEquals('Camp A', Campaign::first()->name);

        AccountContext::clear();
    }

    public function test_super_admin_can_access_dashboard(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }

    public function test_super_admin_dashboard_paginates_partner_platforms(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $super = User::where('email', 'admin@powerbyexcellence.test')->first();

        for ($i = 1; $i <= 5; $i++) {
            Account::create([
                'name' => "Extra Platform {$i}",
                'slug' => "extra-platform-{$i}",
                'default_currency' => 'GBP',
                'default_country' => 'GB',
                'is_active' => true,
            ]);
        }

        $total = Account::count();

        $this->actingAs($super)
            ->get(route('dashboard', ['tenant_page' => 1]))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->has('tenantOverview.data', 10)
                ->where('tenantOverview.total', $total)
                ->where('tenantOverview.current_page', 1)
                ->has('tenantOverview.links')
            );

        $this->actingAs($super)
            ->get(route('dashboard', ['tenant_page' => 2]))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->has('tenantOverview.data', $total - 10)
                ->where('tenantOverview.current_page', 2)
            );
    }

    public function test_super_admin_without_tenant_does_not_see_tenant_scoped_dashboard_metrics(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $super = User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->actingAs($super)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('showTenantDashboard', false)
                ->where('stats', null)
                ->where('charts', null)
                ->where('recentLeads', null)
            );
    }

    public function test_super_admin_dashboard_revenue_is_scoped_to_selected_tenant(): void
    {
        $super = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $uk = Account::create([
            'name' => 'Scoped UK',
            'slug' => 'scoped-uk',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
        ]);
        $us = Account::create([
            'name' => 'Scoped US',
            'slug' => 'scoped-us',
            'default_currency' => 'USD',
            'default_country' => 'US',
            'is_active' => true,
        ]);

        $ukCampaign = Campaign::create([
            'account_id' => $uk->id,
            'name' => 'UK Campaign',
            'reference' => 'scoped-uk-campaign',
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
        ]);
        $usCampaign = Campaign::create([
            'account_id' => $us->id,
            'name' => 'US Campaign',
            'reference' => 'scoped-us-campaign',
            'country' => 'US',
            'currency' => 'USD',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
        ]);

        $ukLead = Lead::create([
            'account_id' => $uk->id,
            'campaign_id' => $ukCampaign->id,
            'status' => 'sold',
            'field_data' => ['email' => 'uk@example.com'],
            'received_at' => now(),
            'distributed_at' => now(),
        ]);
        LeadFinancial::create(['lead_id' => $ukLead->id, 'revenue' => 25, 'payout' => 10, 'margin' => 15, 'currency' => 'GBP']);

        $usLead = Lead::create([
            'account_id' => $us->id,
            'campaign_id' => $usCampaign->id,
            'status' => 'sold',
            'field_data' => ['email' => 'us@example.com'],
            'received_at' => now(),
            'distributed_at' => now(),
        ]);
        LeadFinancial::create(['lead_id' => $usLead->id, 'revenue' => 99, 'payout' => 40, 'margin' => 59, 'currency' => 'USD']);

        $this->actingAs($super)
            ->withSession(['current_account_id' => $uk->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('showTenantDashboard', true)
                ->where('stats.revenue_today', fn ($value) => (float) $value === 25.0)
            );

        $this->actingAs($super)
            ->withSession(['current_account_id' => $us->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('stats.revenue_today', fn ($value) => (float) $value === 99.0)
            );
    }
}
