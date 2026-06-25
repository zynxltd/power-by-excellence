<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Services\Billing\RevenueCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected RevenueCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(RevenueCalculator::class);
    }

    public function test_fixed_pricing(): void
    {
        $delivery = $this->delivery(['revenue_type' => 'fixed', 'revenue_amount' => 15.50]);

        $this->assertEquals(15.50, $this->calculator->calculate($delivery, ['state' => 'CA']));
    }

    public function test_dynamic_pricing_from_response(): void
    {
        $delivery = $this->delivery([
            'revenue_type' => 'dynamic',
            'revenue_amount' => 10,
            'config' => ['revenue_field' => 'Cost'],
        ]);

        $amount = $this->calculator->calculate(
            $delivery,
            [],
            ['Cost' => 22.75],
            ['Cost' => 18],
        );

        $this->assertEquals(22.75, $amount);
    }

    public function test_rule_based_pricing_by_state(): void
    {
        $delivery = $this->delivery([
            'revenue_type' => 'rule_based',
            'revenue_amount' => 12,
            'revenue_rules' => [
                ['field' => 'state', 'value' => 'CA', 'amount' => 25],
                ['field' => 'state', 'value' => 'TX', 'amount' => 18],
            ],
        ]);

        $this->assertEquals(25.0, $this->calculator->calculate($delivery, ['state' => 'CA']));
        $this->assertEquals(18.0, $this->calculator->calculate($delivery, ['state' => 'TX']));
        $this->assertEquals(12.0, $this->calculator->calculate($delivery, ['state' => 'NY']));
    }

    protected function delivery(array $attrs = []): Delivery
    {
        $account = Account::create(['name' => 'T', 'slug' => 't', 'default_currency' => 'GBP', 'default_country' => 'GB']);
        $campaign = Campaign::create(['account_id' => $account->id, 'name' => 'C', 'reference' => 'c-ref']);
        $buyer = Buyer::create(['account_id' => $account->id, 'reference' => 'b', 'name' => 'B']);

        return Delivery::create(array_merge([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Test',
            'method' => DeliveryMethod::StoreLead,
            'status' => 'active',
            'priority' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => 10,
        ], $attrs));
    }
}
