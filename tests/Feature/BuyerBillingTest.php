<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Services\Billing\BuyerBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_and_charge(): void
    {
        $account = Account::create([
            'name' => 'Test', 'slug' => 'test', 'default_currency' => 'GBP', 'default_country' => 'GB',
            'is_active' => true,
            'settings' => ['require_buyer_prepay' => true],
        ]);

        $buyer = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'b1',
            'name' => 'Buyer',
            'status' => 'active',
            'credit_balance' => 100,
        ]);

        $billing = app(BuyerBillingService::class);

        $this->assertTrue($billing->hasCredit($buyer, 50));
        $this->assertNotNull($billing->charge($buyer, 20));
        $this->assertEquals(80, (float) $buyer->fresh()->credit_balance);
        $this->assertNull($billing->charge($buyer, 100));
    }
}
