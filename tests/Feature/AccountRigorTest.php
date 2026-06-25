<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountRigorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_tenant_admin_cannot_manage_partner_platforms(): void
    {
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->get(route('accounts.index'))
            ->assertForbidden();

        $other = Account::where('slug', '!=', 'excellence-uk')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->post(route('accounts.switch'), ['account_id' => $other->id])
            ->assertForbidden();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->post(route('accounts.visit', ['accountId' => $other->id]))
            ->assertForbidden();
    }

    public function test_super_admin_can_switch_tenant_context(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();
        $uk = Account::where('slug', 'excellence-uk')->first();
        $us = Account::where('slug', 'partner-solar-us')->first();

        $this->actingAs($super)
            ->post(route('accounts.switch'), ['account_id' => $us->id])
            ->assertRedirect()
            ->assertSessionHas('current_account_id', $us->id);

        $this->actingAs($super)
            ->withSession(['current_account_id' => $uk->id])
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('currentAccountId', $uk->id));
    }

    public function test_super_admin_switch_requires_valid_account(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->actingAs($super)
            ->post(route('accounts.switch'), ['account_id' => 99999])
            ->assertSessionHasErrors('account_id');
    }

    public function test_tenant_admin_cannot_view_other_tenant_lead(): void
    {
        $ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $usCampaign = Campaign::withoutGlobalScopes()->where('reference', 'auto-insurance-us')->first();
        $this->assertNotNull($usCampaign);
        $usLead = Lead::withoutGlobalScopes()
            ->where('campaign_id', $usCampaign->id)
            ->first();
        $this->assertNotNull($usLead);

        $this->assertNotNull($usLead);

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($ukAdmin)
            ->get('/leads/'.$usLead->id)
            ->assertNotFound();
    }

    public function test_super_admin_on_central_host_can_view_any_tenant_lead(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();
        $usCampaign = Campaign::withoutGlobalScopes()->where('reference', 'auto-insurance-us')->first();
        $this->assertNotNull($usCampaign);
        $usLead = Lead::withoutGlobalScopes()
            ->where('campaign_id', $usCampaign->id)
            ->first();
        $this->assertNotNull($usLead);

        $this->actingAs($super)
            ->get(route('leads.show', $usLead->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Leads/Show')
                ->where('lead.id', $usLead->id)
            );
    }

    public function test_each_seeded_account_has_admin_and_campaigns(): void
    {
        $accounts = Account::where('is_active', true)->get();

        $this->assertGreaterThanOrEqual(10, $accounts->count());

        foreach ($accounts as $account) {
            $this->assertTrue(
                User::where('account_id', $account->id)
                    ->whereIn('role', [UserRole::AccountAdmin, UserRole::Staff])
                    ->exists(),
                "Account {$account->slug} has no admin user"
            );

            $this->assertGreaterThan(
                0,
                Campaign::withoutGlobalScopes()->where('account_id', $account->id)->count(),
                "Account {$account->slug} has no campaigns"
            );
        }
    }

    public function test_tenant_subdomain_resolves_correct_account(): void
    {
        $pairs = [
            'excellence-uk.powerbyexcellence.test' => 'excellence-uk',
            'solar-us.powerbyexcellence.test' => 'partner-solar-us',
            'insurance-ca.powerbyexcellence.test' => 'insurance-ca',
        ];

        foreach ($pairs as $host => $slug) {
            $account = \App\Support\Tenancy\TenantResolver::resolveFromHost($host);
            $this->assertNotNull($account, "Host {$host} did not resolve");
            $this->assertSame($slug, $account->slug);
        }
    }
}
