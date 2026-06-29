<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\BuyerTransaction;
use App\Services\Billing\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StripeCheckoutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->service = app(StripeCheckoutService::class);
    }

    protected function stripeAccount(array $overrides = []): Account
    {
        return new Account(['settings' => ['stripe' => array_merge([
            'enabled' => true,
            'allow_buyer_self_serve' => true,
            'allow_subscriptions' => true,
            'key' => 'pk_test',
            'secret' => 'sk_test',
            'subscription_prices' => [
                ['price_id' => 'price_monthly', 'label' => 'Monthly', 'credit_amount' => 500],
            ],
        ], $overrides)]]);
    }

    public function test_minimum_top_up_defaults_to_one(): void
    {
        $account = new Account(['settings' => []]);

        $this->assertSame(1.0, $this->service->minimumTopUp($account));
    }

    public function test_validate_top_up_rejects_below_minimum(): void
    {
        $account = new Account(['settings' => ['stripe' => ['min_topup' => 25]]]);

        $this->assertSame('Minimum top-up is 25.', $this->service->validateTopUpAmount($account, 10));
        $this->assertNull($this->service->validateTopUpAmount($account, 50));
    }

    public function test_buyer_self_serve_requires_enabled_stripe(): void
    {
        $account = new Account(['settings' => ['stripe' => ['enabled' => false, 'allow_buyer_self_serve' => true]]]);

        $this->assertFalse($this->service->buyerSelfServeEnabled($account));
    }

    public function test_subscriptions_enabled_requires_plans(): void
    {
        $account = $this->stripeAccount(['allow_subscriptions' => true, 'subscription_prices' => []]);

        $this->assertFalse($this->service->subscriptionsEnabled($account));
        $this->assertTrue($this->service->subscriptionsEnabled($this->stripeAccount()));
    }

    public function test_validate_subscription_price_id(): void
    {
        $account = $this->stripeAccount();

        $this->assertNull($this->service->validateSubscriptionPriceId($account, 'price_monthly'));
        $this->assertSame('Invalid subscription plan.', $this->service->validateSubscriptionPriceId($account, 'price_unknown'));
    }

    public function test_subscription_checkout_completed_does_not_credit_balance(): void
    {
        $buyer = Buyer::withoutGlobalScopes()->first();
        $buyer->update(['credit_balance' => 100, 'stripe_customer_id' => null]);

        $session = (object) [
            'id' => 'cs_sub_test',
            'mode' => 'subscription',
            'customer' => 'cus_test_123',
            'subscription' => 'sub_test_123',
            'metadata' => (object) ['buyer_id' => (string) $buyer->id],
        ];

        $result = $this->service->handleWebhookCompleted($session);

        $this->assertNull($result);
        $this->assertSame('cus_test_123', $buyer->fresh()->stripe_customer_id);
        $this->assertSame('sub_test_123', $buyer->fresh()->settings['stripe_subscription']['id']);
        $this->assertSame(100.0, (float) $buyer->fresh()->credit_balance);
    }

    public function test_one_time_checkout_still_credits_balance(): void
    {
        $buyer = Buyer::withoutGlobalScopes()->first();
        $buyer->update(['credit_balance' => 50]);

        $session = (object) [
            'id' => 'cs_pay_test',
            'mode' => 'payment',
            'payment_status' => 'paid',
            'amount_total' => 2500,
            'customer' => 'cus_pay_123',
            'metadata' => (object) ['buyer_id' => (string) $buyer->id],
        ];

        $transaction = $this->service->handleWebhookCompleted($session);

        $this->assertNotNull($transaction);
        $this->assertSame(75.0, (float) $buyer->fresh()->credit_balance);
        $this->assertDatabaseHas('buyer_transactions', [
            'buyer_id' => $buyer->id,
            'description' => 'Stripe checkout top-up',
        ]);
    }

    public function test_invoice_paid_credits_mapped_subscription_amount(): void
    {
        $buyer = Buyer::withoutGlobalScopes()->first();
        $account = $buyer->account;
        $settings = $account->settings ?? [];
        $settings['stripe'] = [
            'enabled' => true,
            'allow_buyer_self_serve' => true,
            'allow_subscriptions' => true,
            'key' => 'pk_test',
            'secret' => 'sk_test',
            'subscription_prices' => [
                ['price_id' => 'price_monthly', 'label' => 'Monthly', 'credit_amount' => 500],
            ],
        ];
        $account->update(['settings' => $settings]);
        $buyer->update(['credit_balance' => 0, 'stripe_customer_id' => 'cus_invoice']);

        $invoice = (object) [
            'id' => 'in_test_123',
            'status' => 'paid',
            'billing_reason' => 'subscription_cycle',
            'amount_paid' => 4999,
            'subscription' => 'sub_test',
            'customer' => 'cus_invoice',
            'metadata' => (object) [],
            'lines' => (object) [
                'data' => [
                    (object) ['price' => (object) ['id' => 'price_monthly']],
                ],
            ],
        ];

        $transaction = $this->service->handleInvoicePaid($invoice);

        $this->assertNotNull($transaction);
        $this->assertSame(500.0, (float) $buyer->fresh()->credit_balance);
    }

    public function test_invoice_paid_is_idempotent(): void
    {
        $buyer = Buyer::withoutGlobalScopes()->first();
        $buyer->update(['credit_balance' => 0, 'stripe_customer_id' => 'cus_dup']);

        BuyerTransaction::create([
            'buyer_id' => $buyer->id,
            'type' => 'credit',
            'amount' => 100,
            'balance_after' => 100,
            'description' => 'Stripe subscription invoice',
            'meta' => ['stripe_invoice_id' => 'in_dup_test'],
        ]);
        $buyer->update(['credit_balance' => 100]);

        $invoice = (object) [
            'id' => 'in_dup_test',
            'status' => 'paid',
            'billing_reason' => 'subscription_cycle',
            'amount_paid' => 10000,
            'customer' => 'cus_dup',
            'metadata' => (object) [],
            'lines' => (object) ['data' => []],
        ];

        $this->service->handleInvoicePaid($invoice);

        $this->assertSame(100.0, (float) $buyer->fresh()->credit_balance);
        $this->assertSame(1, BuyerTransaction::where('buyer_id', $buyer->id)
            ->where('meta->stripe_invoice_id', 'in_dup_test')
            ->count());
    }

    public function test_subscription_updated_syncs_buyer_settings(): void
    {
        $buyer = Buyer::withoutGlobalScopes()->first();
        $buyer->update([
            'stripe_customer_id' => 'cus_sub_sync',
            'settings' => [],
        ]);

        $subscription = (object) [
            'id' => 'sub_sync_1',
            'status' => 'active',
            'cancel_at_period_end' => true,
            'current_period_end' => 1893456000,
            'customer' => 'cus_sub_sync',
            'metadata' => (object) ['buyer_id' => (string) $buyer->id],
            'items' => (object) [
                'data' => [
                    (object) ['price' => (object) ['id' => 'price_monthly']],
                ],
            ],
        ];

        $this->service->handleSubscriptionUpdated($subscription);

        $sub = $buyer->fresh()->settings['stripe_subscription'];
        $this->assertSame('sub_sync_1', $sub['id']);
        $this->assertSame('active', $sub['status']);
        $this->assertTrue($sub['cancel_at_period_end']);
        $this->assertSame('price_monthly', $sub['price_id']);
    }

    public function test_subscription_deleted_clears_buyer_settings(): void
    {
        $buyer = Buyer::withoutGlobalScopes()->first();
        $buyer->update([
            'stripe_customer_id' => 'cus_sub_del',
            'settings' => [
                'stripe_subscription' => [
                    'id' => 'sub_del_1',
                    'status' => 'active',
                ],
            ],
        ]);

        $subscription = (object) [
            'id' => 'sub_del_1',
            'customer' => 'cus_sub_del',
            'metadata' => (object) [],
        ];

        $this->service->handleSubscriptionDeleted($subscription);

        $this->assertArrayNotHasKey('stripe_subscription', $buyer->fresh()->settings ?? []);
    }
}
