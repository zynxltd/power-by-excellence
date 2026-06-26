<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadFinancial;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Billing\BuyerBillingService;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportsFunctionalityTest extends TestCase
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

    public function test_export_routes_map_to_expected_modules(): void
    {
        $this->assertSame('operations', AdminModules::moduleForRoute('leads.export'));
        $this->assertSame('billing', AdminModules::moduleForRoute('billing.export'));
        $this->assertSame('billing', AdminModules::moduleForRoute('billing.export-all'));
    }

    public function test_admin_lead_export_respects_filters_and_quotes_fields(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();

        $included = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Sold,
            'field_data' => [
                'firstname' => 'O\'Brien',
                'lastname' => 'Test',
                'email' => 'export-included@test.test',
                'phone1' => '07700900123',
                'zipcode' => 'SW1A 1AA',
            ],
            'received_at' => now(),
        ]);

        Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => Campaign::where('account_id', $this->ukAccount->id)->where('id', '!=', $campaign->id)->value('id'),
            'status' => LeadStatus::Rejected,
            'field_data' => ['email' => 'export-excluded@test.test'],
            'received_at' => now(),
        ]);

        $response = $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('leads.export', ['campaign_id' => $campaign->id, 'status' => 'sold']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $response->getContent();
        $this->assertStringContainsString($included->uuid, $body);
        $this->assertStringContainsString('"O\'Brien"', $body);
        $this->assertStringNotContainsString('export-excluded@test.test', $body);
    }

    public function test_billing_export_all_is_scoped_to_tenant_and_uses_per_buyer_currency(): void
    {
        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();
        $buyer->update(['currency' => 'EUR']);

        app(BuyerBillingService::class)->adjust($buyer, 25, 'credit', 'Export test credit');

        $response = $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('billing.export-all'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $response->getContent();
        $this->assertStringContainsString($buyer->reference, $body);
        $this->assertStringContainsString('EUR', $body);
        $this->assertStringContainsString('credit', $body);
    }

    public function test_billing_export_single_buyer_returns_ledger_csv(): void
    {
        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();
        app(BuyerBillingService::class)->adjust($buyer, 10, 'goodwill', 'Goodwill export row');

        $response = $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('billing.export', $buyer))
            ->assertOk();

        $body = $response->getContent();
        $this->assertStringContainsString('goodwill', $body);
        $this->assertStringContainsString('Goodwill export row', $body);
    }

    public function test_billing_export_rejects_cross_tenant_buyer(): void
    {
        $usBuyer = Buyer::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'partner-solar-us'))
            ->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('billing.export', $usBuyer))
            ->assertNotFound();
    }

    public function test_buyer_portal_export_only_includes_own_leads_and_quotes_commas(): void
    {
        $buyerA = Buyer::where('account_id', $this->ukAccount->id)->first();
        $buyerB = Buyer::where('account_id', $this->ukAccount->id)->where('id', '!=', $buyerA->id)->first();
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();

        $ownLead = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'sold_to_buyer_id' => $buyerA->id,
            'uuid' => (string) Str::uuid(),
            'status' => LeadStatus::Sold,
            'field_data' => [
                'firstname' => 'Smith, Jr',
                'lastname' => 'Buyer',
                'email' => 'buyer-export@test.test',
                'phone1' => '07700900444',
                'zipcode' => 'EC1A 1BB',
            ],
            'received_at' => now(),
            'distributed_at' => now(),
        ]);

        LeadFinancial::create([
            'lead_id' => $ownLead->id,
            'revenue' => 20,
            'payout' => 5,
            'margin' => 15,
            'currency' => 'GBP',
        ]);

        Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'sold_to_buyer_id' => $buyerB->id,
            'uuid' => (string) Str::uuid(),
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'other-buyer@test.test'],
            'received_at' => now(),
            'distributed_at' => now(),
        ]);

        $portalUser = User::where('email', 'buyer-portal@excellence-uk.test')->first();
        $portalUser->update(['buyer_id' => $buyerA->id]);

        $response = $this->ukHost()
            ->actingAs($portalUser)
            ->get(route('portal.buyer.leads.download'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $response->getContent();
        $this->assertStringContainsString($ownLead->uuid, $body);
        $this->assertStringContainsString('"Smith, Jr"', $body);
        $this->assertStringContainsString('distributed_at', $body);
        $this->assertStringNotContainsString('other-buyer@test.test', $body);
    }

    public function test_supplier_portal_export_scoped_and_includes_payout(): void
    {
        $supplierA = Supplier::where('account_id', $this->ukAccount->id)->first();
        $supplierB = Supplier::create([
            'account_id' => $this->ukAccount->id,
            'reference' => 'export-supplier-b',
            'name' => 'Export Supplier B',
            'status' => 'active',
        ]);

        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();

        $ownLead = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplierA->id,
            'uuid' => (string) Str::uuid(),
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'supplier-export@test.test'],
            'received_at' => now(),
            'distributed_at' => now(),
        ]);

        LeadFinancial::create([
            'lead_id' => $ownLead->id,
            'revenue' => 18,
            'payout' => 6.5,
            'margin' => 11.5,
            'currency' => 'GBP',
        ]);

        Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplierB->id,
            'uuid' => (string) Str::uuid(),
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'other-supplier@test.test'],
            'received_at' => now(),
        ]);

        $portalUser = User::where('email', 'supplier-portal@excellence-uk.test')->first();

        $response = $this->ukHost()
            ->actingAs($portalUser)
            ->get(route('portal.supplier.leads.download'))
            ->assertOk();

        $body = $response->getContent();
        $this->assertStringContainsString($ownLead->uuid, $body);
        $this->assertStringContainsString('6.5', $body);
        $this->assertStringNotContainsString('other-supplier@test.test', $body);
    }

    public function test_buyer_portal_export_filters_by_distributed_date_range(): void
    {
        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $portalUser = User::where('email', 'buyer-portal@excellence-uk.test')->first();
        $portalUser->update(['buyer_id' => $buyer->id]);

        $inRange = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'sold_to_buyer_id' => $buyer->id,
            'uuid' => (string) Str::uuid(),
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'in-range@test.test'],
            'received_at' => now()->subDays(2),
            'distributed_at' => now()->subDay(),
        ]);

        Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'sold_to_buyer_id' => $buyer->id,
            'uuid' => (string) Str::uuid(),
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'out-range@test.test'],
            'received_at' => now()->subDays(40),
            'distributed_at' => now()->subDays(30),
        ]);

        $from = now()->subDays(3)->toDateString();
        $to = now()->toDateString();

        $body = $this->ukHost()
            ->actingAs($portalUser)
            ->get(route('portal.buyer.leads.download', ['from_date' => $from, 'to_date' => $to]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($inRange->uuid, $body);
        $this->assertStringNotContainsString('out-range@test.test', $body);
    }
}
