<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Source;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuppliersFunctionalityTest extends TestCase
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

    public function test_suppliers_routes_map_to_tenant_module(): void
    {
        $this->assertSame('tenant', AdminModules::moduleForRoute('suppliers.index'));
        $this->assertSame('tenant', AdminModules::moduleForRoute('suppliers.store'));
    }

    public function test_create_supplier_normalizes_reference_and_sids(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('suppliers.store'), [
                'reference' => 'NEW-SUPPLIER-01',
                'name' => 'New Supplier Ltd',
                'status' => 'active',
                'sources' => [
                    ['sid' => 'Google_Search', 'name' => 'Google Search'],
                ],
                'rev_share_percent' => 15,
                'default_postback_url' => 'https://example.com/postback',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('suppliers', [
            'account_id' => $this->ukAccount->id,
            'reference' => 'new-supplier-01',
            'name' => 'New Supplier Ltd',
        ]);

        $supplier = Supplier::where('reference', 'new-supplier-01')->first();
        $this->assertSame('google_search', $supplier->sources->first()->sid);
        $this->assertEquals(15.0, $supplier->affiliate_settings['rev_share_percent']);
        $this->assertSame('https://example.com/postback', $supplier->affiliate_settings['default_postback_url']);
    }

    public function test_duplicate_reference_within_tenant_is_rejected(): void
    {
        $existing = Supplier::where('account_id', $this->ukAccount->id)->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('suppliers.store'), [
                'reference' => $existing->reference,
                'name' => 'Duplicate Ref',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('reference');
    }

    public function test_supplier_show_page_has_coherent_operational_context(): void
    {
        $supplier = Supplier::where('account_id', $this->ukAccount->id)->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Suppliers/Show')
                ->where('supplier.id', $supplier->id)
                ->has('leadStats')
                ->has('recentLeads')
            );
    }

    public function test_supplier_index_stats_are_tenant_scoped(): void
    {
        $ukCount = Supplier::where('account_id', $this->ukAccount->id)->count();
        $ukActive = Supplier::where('account_id', $this->ukAccount->id)->where('status', 'active')->count();

        $response = $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('suppliers.index'))
            ->assertOk();

        $stats = $response->viewData('page')['props']['stats'];
        $this->assertSame($ukCount, $stats['total']);
        $this->assertSame($ukActive, $stats['active']);

        $names = collect($response->viewData('page')['props']['suppliers']['data'])->pluck('name')->implode(' ');
        $this->assertStringContainsString('Affiliate Horizon UK', $names);
        $this->assertStringNotContainsString('Solar Media Partners', $names);
    }

    public function test_supplier_index_search_filters_by_sid(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('suppliers.index', ['search' => 'google_search']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('suppliers.data', fn ($rows) => count($rows) >= 1
                    && collect($rows)->contains(fn ($row) => collect($row['sources'] ?? [])
                        ->contains(fn ($src) => str_contains($src['sid'], 'google_search'))))
            );
    }

    public function test_sub_suppliers_sync_on_update(): void
    {
        $supplier = Supplier::create([
            'account_id' => $this->ukAccount->id,
            'reference' => 'sub-supplier-test',
            'name' => 'Sub Supplier Test',
            'status' => 'active',
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->put(route('suppliers.update', $supplier), [
                'reference' => 'sub-supplier-test',
                'name' => 'Sub Supplier Test',
                'status' => 'active',
                'enable_sub_suppliers' => true,
                'sources' => [
                    [
                        'sid' => 'main_sid',
                        'name' => 'Main SID',
                        'sub_suppliers' => [
                            ['ssid' => 'Sub_A', 'name' => 'Sub A'],
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $source = Source::where('supplier_id', $supplier->id)->where('sid', 'main_sid')->first();
        $this->assertNotNull($source);
        $this->assertDatabaseHas('sub_suppliers', [
            'source_id' => $source->id,
            'ssid' => 'sub_a',
            'name' => 'Sub A',
        ]);
    }

    public function test_portal_user_can_be_created_with_supplier(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('suppliers.store'), [
                'reference' => 'portal-supplier',
                'name' => 'Portal Supplier Co',
                'status' => 'active',
                'sources' => [['sid' => 'portal_sid', 'name' => 'Portal SID']],
                'portal_email' => 'portal-supplier-co@test.test',
                'portal_name' => 'Portal User',
                'portal_password' => 'password123',
            ])
            ->assertRedirect();

        $supplier = Supplier::where('reference', 'portal-supplier')->first();
        $this->assertNotNull($supplier);

        $this->assertDatabaseHas('users', [
            'supplier_id' => $supplier->id,
            'email' => 'portal-supplier-co@test.test',
            'role' => 'supplier_portal',
        ]);
    }

    public function test_supplier_portal_dashboard_uses_account_currency(): void
    {
        $supplierUser = User::where('email', 'supplier-portal@excellence-uk.test')->first();

        $this->ukHost()
            ->actingAs($supplierUser)
            ->get(route('portal.supplier.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Supplier/Dashboard')
                ->where('currency', 'GBP')
                ->has('stats.leads_today')
                ->has('stats.sold_today')
                ->has('stats.revenue_today')
                ->has('charts.labels', 7)
            );
    }

    public function test_supplier_portal_only_sees_own_leads(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();

        $supplierA = Supplier::create([
            'account_id' => $this->ukAccount->id,
            'reference' => 'supplier-a',
            'name' => 'Supplier A',
            'status' => 'active',
        ]);

        $supplierB = Supplier::create([
            'account_id' => $this->ukAccount->id,
            'reference' => 'supplier-b',
            'name' => 'Supplier B',
            'status' => 'active',
        ]);

        Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplierA->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'sold',
            'field_data' => ['email' => 'a-only@test.test'],
            'received_at' => now(),
        ]);

        Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplierB->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'sold',
            'field_data' => ['email' => 'b-only@test.test'],
            'received_at' => now(),
        ]);

        $portalUser = User::factory()->create([
            'account_id' => $this->ukAccount->id,
            'supplier_id' => $supplierA->id,
            'role' => UserRole::SupplierPortal,
            'email' => 'supplier-a-portal@test.test',
        ]);

        $this->ukHost()
            ->actingAs($portalUser)
            ->get(route('portal.supplier.leads'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('leads.data', fn ($rows) => count($rows) === 1
                    && $rows[0]['field_data']['email'] === 'a-only@test.test')
            );
    }

    public function test_supplier_portal_unlinked_user_is_forbidden(): void
    {
        $orphan = User::factory()->create([
            'account_id' => $this->ukAccount->id,
            'supplier_id' => null,
            'role' => UserRole::SupplierPortal,
            'email' => 'orphan-supplier@test.test',
        ]);

        $this->ukHost()
            ->actingAs($orphan)
            ->get(route('portal.supplier.dashboard'))
            ->assertForbidden();

        $this->ukHost()
            ->actingAs($orphan)
            ->get(route('portal.supplier.billing'))
            ->assertForbidden();
    }

    public function test_supplier_portal_user_can_save_theme_preferences(): void
    {
        $supplier = User::where('email', 'supplier-portal@excellence-uk.test')->first();

        $this->ukHost()
            ->actingAs($supplier)
            ->patch('/profile/preferences', [
                'theme' => 'dark',
                'accent_color' => 'emerald',
            ])
            ->assertRedirect();

        $supplier->refresh();
        $this->assertSame('dark', $supplier->theme);
        $this->assertSame('emerald', $supplier->accent_color);

        $this->ukHost()
            ->actingAs($supplier)
            ->get(route('portal.supplier.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.preferences.theme', 'dark')
                ->where('auth.preferences.accent_color', 'emerald')
            );
    }

    public function test_supplier_billing_summary_reflects_sold_lead_payouts(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $supplier = Supplier::where('account_id', $this->ukAccount->id)->first();

        $lead = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplier->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'sold',
            'field_data' => ['email' => 'payout@test.test'],
            'received_at' => now(),
            'distributed_at' => now(),
        ]);

        $lead->financials()->create(['payout' => 42.50, 'revenue' => 50.00, 'cost' => 7.50]);

        $supplierUser = User::where('email', 'supplier-portal@excellence-uk.test')->first();

        $this->ukHost()
            ->actingAs($supplierUser)
            ->get(route('portal.supplier.billing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currency', 'GBP')
                ->where('summary.total_payout', fn ($v) => (float) $v >= 42.50)
                ->where('summary.sold_count', fn ($v) => $v >= 1)
            );
    }
}
