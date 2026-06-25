<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Enums\DeliveryMethod;
use App\Services\Billing\BuyerBillingService;
use App\Services\Distribution\RoutingSimulatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerPrepayEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepay_blocks_buyer_without_credit_in_routing(): void
    {
        $account = Account::create([
            'name' => 'Prepay Test',
            'slug' => 'prepay-test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
            'settings' => ['require_buyer_prepay' => true],
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'Test Campaign',
            'reference' => 'prepay-campaign',
            'status' => 'active',
            'country' => 'GB',
            'currency' => 'GBP',
            'payout_amount' => 5,
            'floor_price' => 0,
        ]);

        $buyer = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'broke-buyer',
            'name' => 'Broke Buyer',
            'status' => 'active',
            'credit_balance' => 0,
        ]);

        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'API Delivery',
            'method' => DeliveryMethod::StoreLead,
            'status' => 'active',
            'priority' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => 15,
        ]);

        $billing = app(BuyerBillingService::class);
        $this->assertFalse($billing->hasCredit($buyer, 15));

        $simulator = app(RoutingSimulatorService::class);
        $result = $simulator->simulate($campaign, [
            'firstname' => 'Test',
            'lastname' => 'Lead',
            'email' => 'test@example.com',
            'phone1' => '07700900123',
        ]);

        $step = collect($result['steps'])->firstWhere('delivery_id', $delivery->id);
        $this->assertNotNull($step);
        $this->assertFalse($step['eligible']);
        $this->assertContains('Insufficient buyer credit', $step['skip_reasons']);
    }

    public function test_prepay_allows_buyer_with_credit(): void
    {
        $account = Account::create([
            'name' => 'Prepay Test 2',
            'slug' => 'prepay-test-2',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
            'settings' => ['require_buyer_prepay' => true],
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'Test Campaign',
            'reference' => 'prepay-campaign-2',
            'status' => 'active',
            'country' => 'GB',
            'currency' => 'GBP',
            'payout_amount' => 5,
            'floor_price' => 0,
        ]);

        $buyer = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'funded-buyer',
            'name' => 'Funded Buyer',
            'status' => 'active',
            'credit_balance' => 100,
        ]);

        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'API Delivery',
            'method' => DeliveryMethod::StoreLead,
            'status' => 'active',
            'priority' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => 15,
        ]);

        $this->assertTrue(app(BuyerBillingService::class)->hasCredit($buyer, 15));

        $result = app(RoutingSimulatorService::class)->simulate($campaign, [
            'firstname' => 'Test',
            'email' => 'test@example.com',
            'phone1' => '07700900123',
        ]);

        $step = collect($result['steps'])->firstWhere('delivery_id', $delivery->id);
        $this->assertTrue($step['eligible']);
    }

    public function test_settings_prepay_toggle_persists(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $admin = \App\Models\User::where('email', 'uk@powerbyexcellence.test')->first();
        $account = $admin->account;

        $this->actingAs($admin)
            ->put(route('settings.update'), [
                'name' => $account->name,
                'timezone' => $account->timezone ?? 'Europe/London',
                'default_country' => $account->default_country,
                'default_currency' => $account->default_currency,
                'require_buyer_prepay' => true,
            ])
            ->assertRedirect();

        $account->refresh();
        $this->assertTrue($account->settings['require_buyer_prepay'] ?? false);
    }
}
