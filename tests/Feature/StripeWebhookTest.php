<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\Billing\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $this->mock(StripeCheckoutService::class, function ($mock) {
            $mock->shouldReceive('constructWebhookEvent')
                ->once()
                ->andThrow(new \InvalidArgumentException('bad signature'));
        });

        $this->post('/stripe/webhook', [], ['Stripe-Signature' => 'invalid'])
            ->assertStatus(400);
    }

    public function test_webhook_dispatches_invoice_paid_handler(): void
    {
        $invoice = (object) ['id' => 'in_dispatch_1'];

        $this->mock(StripeCheckoutService::class, function ($mock) use ($invoice) {
            $mock->shouldReceive('constructWebhookEvent')
                ->once()
                ->andReturn((object) [
                    'type' => 'invoice.paid',
                    'data' => (object) ['object' => $invoice],
                ]);
            $mock->shouldReceive('handleInvoicePaid')
                ->once()
                ->with($invoice);
        });

        $this->post('/stripe/webhook', ['id' => 'evt_1'], ['Stripe-Signature' => 'sig_test'])
            ->assertOk()
            ->assertSee('OK');
    }

    public function test_webhook_dispatches_subscription_lifecycle_handlers(): void
    {
        $subscription = (object) ['id' => 'sub_dispatch_1'];

        $this->mock(StripeCheckoutService::class, function ($mock) use ($subscription) {
            $mock->shouldReceive('constructWebhookEvent')
                ->once()
                ->andReturn((object) [
                    'type' => 'customer.subscription.updated',
                    'data' => (object) ['object' => $subscription],
                ]);
            $mock->shouldReceive('handleSubscriptionUpdated')
                ->once()
                ->with($subscription);
        });

        $this->post('/stripe/webhook', [], ['Stripe-Signature' => 'sig_test'])
            ->assertOk();
    }

    public function test_buyer_billing_includes_subscription_props_when_enabled(): void
    {
        $account = Account::where('slug', 'excellence-uk')->first();
        $settings = $account->settings ?? [];
        $settings['stripe'] = [
            'enabled' => true,
            'allow_buyer_self_serve' => true,
            'allow_subscriptions' => true,
            'key' => 'pk_test_fake',
            'secret' => 'sk_test_fake',
            'subscription_prices' => [
                ['price_id' => 'price_monthly', 'label' => 'Monthly credit', 'credit_amount' => 500],
            ],
        ];
        $account->update(['settings' => $settings]);

        $portalUser = User::where('email', 'buyer-portal@excellence-uk.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($portalUser)
            ->get(route('portal.buyer.billing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Buyer/Billing')
                ->where('stripeSubscriptionsEnabled', true)
                ->has('stripeSubscriptionPlans', 1)
            );
    }
}
