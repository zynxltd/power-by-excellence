<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\BuyerTransaction;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\BuyerBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BillingFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $ukAdmin;

    protected User $superAdmin;

    protected Account $ukAccount;

    protected Buyer $ukBuyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->superAdmin = User::where('email', 'admin@powerbyexcellence.test')->first();
        $this->ukAccount = Account::where('slug', 'excellence-uk')->first();
        $this->ukBuyer = Buyer::where('account_id', $this->ukAccount->id)->first();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    protected function centralHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'powerbyexcellence.test']);
    }

    public function test_billing_routes_map_to_billing_module(): void
    {
        $this->assertSame('billing', \App\Support\AdminModules::moduleForRoute('billing.index'));
        $this->assertSame('billing', \App\Support\AdminModules::moduleForRoute('billing.show'));
        $this->assertSame('billing', \App\Support\AdminModules::moduleForRoute('billing.top-up'));
    }

    public function test_billing_index_summary_and_transactions_are_tenant_scoped(): void
    {
        $otherAccount = Account::where('slug', 'insurance-ca')->first();
        $otherBuyer = Buyer::withoutGlobalScopes()->where('account_id', $otherAccount->id)->first();

        BuyerTransaction::create([
            'buyer_id' => $otherBuyer->id,
            'type' => 'credit',
            'amount' => 999,
            'balance_after' => 999,
            'description' => 'Foreign tenant credit',
        ]);

        $ukCreditTotal = (float) Buyer::where('account_id', $this->ukAccount->id)->sum('credit_balance');
        $ukBuyerCount = Buyer::where('account_id', $this->ukAccount->id)->count();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Billing/Index')
                ->where('summary.total_credit', fn ($v) => (float) $v === $ukCreditTotal)
                ->where('summary.buyer_count', $ukBuyerCount)
                ->where('summary.currency', 'GBP')
                ->has('accountBilling')
                ->where('recentTransactions.data', fn ($rows) => collect($rows)->every(
                    fn ($row) => ($row['buyer']['account_id'] ?? null) === $this->ukAccount->id
                ))
                ->where('recentTransactions.data', fn ($rows) => collect($rows)->pluck('description')->doesntContain('Foreign tenant credit'))
            );
    }

    public function test_super_admin_on_central_without_tenant_gets_403_on_billing(): void
    {
        $this->centralHost()
            ->actingAs($this->superAdmin)
            ->get(route('billing.index'))
            ->assertForbidden();
    }

    public function test_billing_show_and_top_up_update_ledger(): void
    {
        $before = (float) $this->ukBuyer->credit_balance;

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('billing.show', $this->ukBuyer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Billing/Show')
                ->where('buyer.id', $this->ukBuyer->id)
                ->has('ledgerTypes')
                ->has('transactions')
            );

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('billing.top-up', $this->ukBuyer), [
                'amount' => 50,
                'type' => 'credit',
                'description' => 'Test top-up',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->ukBuyer->refresh();
        $this->assertEquals($before + 50, (float) $this->ukBuyer->credit_balance);

        $this->assertDatabaseHas('buyer_transactions', [
            'buyer_id' => $this->ukBuyer->id,
            'type' => 'credit',
            'description' => 'Test top-up',
        ]);
    }

    public function test_manual_debit_rejects_insufficient_balance_without_allow_negative(): void
    {
        $this->ukBuyer->update(['credit_balance' => 5]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('billing.top-up', $this->ukBuyer), [
                'amount' => 20,
                'type' => 'manual_debit',
                'description' => 'Overdraft attempt',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertEquals(5, (float) $this->ukBuyer->fresh()->credit_balance);
    }

    public function test_locked_account_blocks_debit_without_bypass(): void
    {
        app(AccountBillingService::class)->lock($this->ukAccount->fresh(), 'Test lock');
        $before = (float) $this->ukBuyer->credit_balance;

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('billing.top-up', $this->ukBuyer), [
                'amount' => 10,
                'type' => 'manual_debit',
                'description' => 'Should fail',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertEquals($before, (float) $this->ukBuyer->fresh()->credit_balance);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('billing.top-up', $this->ukBuyer), [
                'amount' => 5,
                'type' => 'manual_debit',
                'description' => 'Locked debit with bypass',
                'bypass_account_lock' => true,
            ])
            ->assertRedirect();

        $this->assertEquals($before - 5, (float) $this->ukBuyer->fresh()->credit_balance);
    }

    public function test_billing_show_and_top_up_reject_cross_tenant_buyer(): void
    {
        $usBuyer = Buyer::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'partner-solar-us'))
            ->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('billing.show', $usBuyer))
            ->assertNotFound();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('billing.top-up', $usBuyer), [
                'amount' => 10,
                'type' => 'credit',
            ])
            ->assertNotFound();
    }

    public function test_account_unlock_restores_dashboard_via_web_route(): void
    {
        app(AccountBillingService::class)->lock($this->ukAccount->fresh(), 'Unlock test');

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('dashboard'))
            ->assertRedirect(route('billing.lock'));

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('billing.unlock'))
            ->assertRedirect(route('dashboard'));

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_locked_account_can_still_export_billing_csv(): void
    {
        app(AccountBillingService::class)->lock($this->ukAccount->fresh(), 'Export while locked');
        app(BuyerBillingService::class)->adjust($this->ukBuyer, 5, 'credit', 'Locked export row');

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('billing.export', $this->ukBuyer))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('billing.export-all'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($this->ukBuyer->reference, $body);
    }

    public function test_buyer_portal_billing_shows_only_own_transactions(): void
    {
        $otherBuyer = Buyer::where('account_id', $this->ukAccount->id)
            ->where('id', '!=', $this->ukBuyer->id)
            ->first();

        BuyerTransaction::create([
            'buyer_id' => $this->ukBuyer->id,
            'type' => 'credit',
            'amount' => 12,
            'balance_after' => 12,
            'description' => 'Own buyer credit',
        ]);

        BuyerTransaction::create([
            'buyer_id' => $otherBuyer->id,
            'type' => 'credit',
            'amount' => 99,
            'balance_after' => 99,
            'description' => 'Other buyer credit',
        ]);

        $portalUser = User::where('email', 'buyer-portal@excellence-uk.test')->first();
        $portalUser->update(['buyer_id' => $this->ukBuyer->id]);

        $this->ukHost()
            ->actingAs($portalUser)
            ->get(route('portal.buyer.billing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Buyer/Billing')
                ->where('buyer.id', $this->ukBuyer->id)
                ->where('transactions.data', fn ($rows) => collect($rows)->pluck('description')->contains('Own buyer credit'))
                ->where('transactions.data', fn ($rows) => collect($rows)->pluck('description')->doesntContain('Other buyer credit'))
            );
    }

    public function test_prepay_charge_creates_debit_transaction(): void
    {
        $account = $this->ukAccount->fresh();
        $settings = $account->settings ?? [];
        $settings['require_buyer_prepay'] = true;
        $account->update(['settings' => $settings]);

        $buyer = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'prepay-test',
            'name' => 'Prepay Test Buyer',
            'status' => 'active',
            'credit_balance' => 100,
        ]);

        $billing = app(BuyerBillingService::class);
        $this->assertTrue($billing->charge($buyer, 25, null, 'Lead purchase test'));

        $buyer->refresh();
        $this->assertEquals(75, (float) $buyer->credit_balance);

        $this->assertDatabaseHas('buyer_transactions', [
            'buyer_id' => $buyer->id,
            'type' => 'debit',
            'amount' => -25,
            'description' => 'Lead purchase test',
        ]);
    }
}
