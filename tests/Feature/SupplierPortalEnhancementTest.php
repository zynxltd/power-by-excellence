<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPortalEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;

    protected Supplier $supplier;

    protected User $portalUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $this->account = Account::where('slug', 'excellence-uk')->firstOrFail();
        $this->supplier = Supplier::where('account_id', $this->account->id)->firstOrFail();
        $this->portalUser = User::where('email', 'supplier-portal@excellence-uk.test')->firstOrFail();
    }

    protected function host()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_supplier_can_view_lead_detail_page(): void
    {
        $lead = Lead::where('supplier_id', $this->supplier->id)->firstOrFail();
        $lead->update(['source' => 'hosted_form:test-form']);

        $this->host()
            ->actingAs($this->portalUser)
            ->get(route('portal.supplier.leads.show', $lead->uuid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Supplier/Show')
                ->where('lead.uuid', $lead->uuid)
                ->where('lead.ingest_source', 'hosted_form:test-form')
                ->has('lead.fields')
            );
    }

    public function test_supplier_cannot_view_another_suppliers_lead(): void
    {
        $campaign = Campaign::where('account_id', $this->account->id)->firstOrFail();

        $otherSupplier = Supplier::create([
            'account_id' => $this->account->id,
            'reference' => 'other-supplier-portal',
            'name' => 'Other Supplier',
            'status' => 'active',
        ]);

        $lead = Lead::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $otherSupplier->id,
            'status' => 'sold',
            'field_data' => ['email' => 'other@test.test', 'firstname' => 'Other', 'lastname' => 'Lead'],
            'received_at' => now(),
            'distributed_at' => now(),
        ]);

        $this->host()
            ->actingAs($this->portalUser)
            ->get(route('portal.supplier.leads.show', $lead->uuid))
            ->assertNotFound();
    }

    public function test_dashboard_includes_account_summary_and_source_performance(): void
    {
        $this->host()
            ->actingAs($this->portalUser)
            ->get(route('portal.supplier.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Supplier/Dashboard')
                ->has('account.status')
                ->has('account.sources')
                ->has('recentLeads')
                ->has('recentActivity')
                ->has('sourcePerformance')
                ->has('stats.sold_rate')
                ->has('stats.payout_7d')
            );
    }

    public function test_billing_includes_paginated_payouts(): void
    {
        $this->host()
            ->actingAs($this->portalUser)
            ->get(route('portal.supplier.billing'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Supplier/Billing')
                ->has('payouts.data')
                ->has('filters')
                ->has('campaigns')
                ->has('sids')
                ->has('account.reference')
                ->has('stats.payout_30d')
            );
    }

    public function test_billing_filters_payouts_by_date(): void
    {
        $campaign = Campaign::where('account_id', $this->account->id)->firstOrFail();

        $oldLead = Lead::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $this->supplier->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'sold',
            'field_data' => ['email' => 'old-payout@test.test'],
            'received_at' => now()->subDays(10),
            'distributed_at' => now()->subDays(10),
        ]);
        $oldLead->financials()->create(['payout' => 10.00, 'revenue' => 12.00, 'cost' => 2.00]);

        $recentLead = Lead::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $this->supplier->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'sold',
            'field_data' => ['email' => 'recent-payout@test.test'],
            'received_at' => now(),
            'distributed_at' => now(),
        ]);
        $recentLead->financials()->create(['payout' => 20.00, 'revenue' => 24.00, 'cost' => 4.00]);

        $from = now()->subDays(2)->toDateString();
        $to = now()->toDateString();

        $this->host()
            ->actingAs($this->portalUser)
            ->get(route('portal.supplier.billing', ['from_date' => $from, 'to_date' => $to]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.from_date', $from)
                ->where('filters.to_date', $to)
                ->where('payouts.data', fn ($rows) => collect($rows)->contains('uuid', $recentLead->uuid)
                    && ! collect($rows)->contains('uuid', $oldLead->uuid))
            );
    }

    public function test_leads_page_includes_sid_filter_options(): void
    {
        $this->host()
            ->actingAs($this->portalUser)
            ->get(route('portal.supplier.leads'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Supplier/Leads')
                ->has('sids')
                ->has('account')
            );
    }
}
