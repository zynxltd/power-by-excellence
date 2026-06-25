<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Lead;
use App\Models\User;
use App\Services\Api\ApiKeyService;
use App\Services\Billing\AccountBillingService;
use App\Services\Buyers\BuyerEligibilityService;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BuyersFunctionalityTest extends TestCase
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

    public function test_buyers_routes_map_to_tenant_module(): void
    {
        $this->assertSame('tenant', AdminModules::moduleForRoute('buyers.index'));
        $this->assertSame('tenant', AdminModules::moduleForRoute('buyers.store'));
    }

    public function test_create_buyer_normalizes_reference_and_persists_caps(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('buyers.store'), [
                'reference' => 'NEW-BUYER-01',
                'name' => 'New Buyer Ltd',
                'email' => 'newbuyer@test.test',
                'status' => 'active',
                'credit_balance' => 500,
                'caps' => ['daily' => 25, 'daily_spend_cap' => 1000],
                'settings' => ['min_quality_score' => 70, 'notify_on_sale' => true],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('buyers', [
            'account_id' => $this->ukAccount->id,
            'reference' => 'new-buyer-01',
            'name' => 'New Buyer Ltd',
        ]);

        $buyer = Buyer::where('reference', 'new-buyer-01')->first();
        $this->assertSame(25, $buyer->caps['daily']);
        $this->assertSame(70, $buyer->settings['min_quality_score']);
        $this->assertTrue($buyer->settings['notify_on_sale']);
    }

    public function test_duplicate_reference_within_tenant_is_rejected(): void
    {
        $existing = Buyer::where('account_id', $this->ukAccount->id)->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('buyers.store'), [
                'reference' => $existing->reference,
                'name' => 'Duplicate Ref',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('reference');
    }

    public function test_buyer_show_page_has_coherent_operational_context(): void
    {
        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('buyers.show', $buyer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Buyers/Show')
                ->where('buyer.id', $buyer->id)
                ->where('currency', 'GBP')
                ->has('isOperational')
                ->has('recentLeads')
                ->has('recentTransactions')
            );
    }

    public function test_buyer_index_stats_and_credit_are_tenant_scoped(): void
    {
        $ukTotal = (float) Buyer::where('account_id', $this->ukAccount->id)->sum('credit_balance');
        $ukCount = Buyer::where('account_id', $this->ukAccount->id)->count();

        $response = $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('buyers.index'))
            ->assertOk();

        $stats = $response->viewData('page')['props']['stats'];
        $this->assertSame($ukCount, $stats['total']);
        $this->assertEquals($ukTotal, (float) $stats['total_credit']);

        $names = collect($response->viewData('page')['props']['buyers']['data'])->pluck('name')->implode(' ');
        $this->assertStringNotContainsString('State Farm', $names);
        $this->assertStringContainsString('Aviva', $names);
    }

    public function test_buyer_index_search_filters_by_name(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('buyers.index', ['search' => 'Aviva']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('buyers.data', fn ($rows) => count($rows) >= 1
                    && collect($rows)->every(fn ($row) => str_contains(strtolower($row['name']), 'aviva')))
            );
    }

    public function test_inactive_buyer_is_not_operational(): void
    {
        $buyer = Buyer::create([
            'account_id' => $this->ukAccount->id,
            'reference' => 'inactive-buyer',
            'name' => 'Inactive Buyer',
            'status' => 'inactive',
            'credit_balance' => 500,
        ]);

        $this->assertFalse(app(AccountBillingService::class)->isBuyerOperational($buyer));
    }

    public function test_geo_restriction_blocks_out_of_region_leads(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $buyer = Buyer::create([
            'account_id' => $this->ukAccount->id,
            'reference' => 'geo-buyer',
            'name' => 'Geo Buyer',
            'status' => 'active',
            'credit_balance' => 100,
            'settings' => ['geo_countries' => ['US']],
        ]);

        $delivery = Delivery::where('campaign_id', $campaign->id)->first();
        $delivery->update(['buyer_id' => $buyer->id]);

        $lead = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'status' => 'accepted',
            'field_data' => ['email' => 'geo@test.test', 'country' => 'GB'],
            'received_at' => now(),
        ])->load('campaign');

        $this->assertFalse(
            app(BuyerEligibilityService::class)->canDeliver($lead, $delivery->fresh(['buyer']))
        );
    }

    public function test_quality_score_reflects_missing_fields(): void
    {
        $lead = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => Campaign::where('account_id', $this->ukAccount->id)->value('id'),
            'status' => 'accepted',
            'field_data' => ['email' => 'sparse@test.test'],
            'metadata' => ['email_validation' => ['passed' => false]],
            'received_at' => now(),
        ]);

        $score = BuyerEligibilityService::computeQualityScore($lead);

        $this->assertLessThan(100, $score);
        $this->assertGreaterThanOrEqual(0, $score);
    }

    public function test_api_credit_rejects_cross_tenant_buyer(): void
    {
        $token = app(ApiKeyService::class)->create([
            'account_id' => $this->ukAccount->id,
            'name' => 'UK buyers',
            'type' => 'administrator',
            'permissions' => ['buyers.manage'],
        ])['token'];

        $usBuyer = Buyer::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'partner-solar-us'))
            ->first();

        $this->postJson('/api/v1/buyers/'.$usBuyer->id.'/credit', [
            'amount' => 50,
        ], ['Authorization' => 'Bearer '.$token])
            ->assertNotFound();
    }

    public function test_api_feedback_rejects_lead_not_sold_to_buyer(): void
    {
        $buyerA = Buyer::where('account_id', $this->ukAccount->id)->first();
        $buyerB = Buyer::where('account_id', $this->ukAccount->id)->where('id', '!=', $buyerA->id)->first();

        $lead = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => Campaign::where('account_id', $this->ukAccount->id)->value('id'),
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'sold_to_buyer_id' => $buyerA->id,
            'status' => 'sold',
            'field_data' => ['email' => 'feedback-scope@test.test'],
            'received_at' => now(),
            'distributed_at' => now(),
        ]);

        $token = app(ApiKeyService::class)->create([
            'account_id' => $this->ukAccount->id,
            'name' => 'UK feedback',
            'type' => 'administrator',
            'permissions' => ['buyers.manage'],
        ])['token'];

        $this->postJson('/api/v1/buyers/'.$buyerB->id.'/feedback', [
            'lead_uuid' => $lead->uuid,
            'status' => 'contacted',
        ], ['Authorization' => 'Bearer '.$token])
            ->assertNotFound();
    }

    public function test_portal_user_can_be_created_with_buyer(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('buyers.store'), [
                'reference' => 'portal-buyer',
                'name' => 'Portal Buyer Co',
                'status' => 'active',
                'credit_balance' => 100,
                'portal_email' => 'portal-buyer-co@test.test',
                'portal_name' => 'Portal User',
                'portal_password' => 'password123',
            ])
            ->assertRedirect();

        $buyer = Buyer::where('reference', 'portal-buyer')->first();
        $this->assertNotNull($buyer);

        $this->assertDatabaseHas('users', [
            'buyer_id' => $buyer->id,
            'email' => 'portal-buyer-co@test.test',
            'role' => 'buyer_portal',
        ]);
    }

    public function test_resolved_currency_uses_buyer_currency_then_account_default(): void
    {
        $buyer = Buyer::create([
            'account_id' => $this->ukAccount->id,
            'reference' => 'currency-buyer',
            'name' => 'Currency Buyer',
            'status' => 'active',
            'currency' => 'EUR',
        ]);

        $this->assertSame('EUR', $buyer->resolvedCurrency());

        $buyer->update(['currency' => null]);
        $this->assertSame('GBP', $buyer->fresh()->resolvedCurrency());
    }
}
