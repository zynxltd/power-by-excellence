<?php

namespace Tests\Feature;

use App\Mail\BuyerInvoiceMail;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\BuyerInvoice;
use App\Models\BuyerTransaction;
use App\Models\User;
use App\Services\Billing\BuyerInvoiceService;
use App\Services\Billing\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class BuyerInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->registerBuyerInvoiceRoutes();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function registerBuyerInvoiceRoutes(): void
    {
        require_once base_path('routes/compliance-phase-3.php');

        if (! Route::has('portal.buyer.invoices.resend')) {
            Route::post('portal/buyer/invoices/{invoice}/resend', [\App\Http\Controllers\Portal\BuyerPortalController::class, 'resendInvoice'])
                ->middleware('web')
                ->name('portal.buyer.invoices.resend');
        }
    }

    protected function stripeAccount(Account $account): void
    {
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
    }

    public function test_finalized_webhook_creates_record_and_queues_email(): void
    {
        Mail::fake();

        $buyer = Buyer::withoutGlobalScopes()->first();
        $buyer->update([
            'stripe_customer_id' => 'cus_finalized_test',
            'email' => 'buyer-invoice@test.com',
        ]);

        $invoice = (object) [
            'id' => 'in_finalized_test',
            'status' => 'open',
            'currency' => 'gbp',
            'amount_due' => 2500,
            'customer' => 'cus_finalized_test',
            'invoice_pdf' => 'https://pay.stripe.com/invoice/pdf/finalized',
            'metadata' => (object) [],
        ];

        $this->partialMock(StripeCheckoutService::class, function ($mock) use ($invoice) {
            $mock->shouldReceive('constructWebhookEvent')
                ->once()
                ->andReturn((object) [
                    'type' => 'invoice.finalized',
                    'data' => (object) ['object' => $invoice],
                ]);
        });

        $this->post('/stripe/webhook', ['id' => 'evt_finalized'], ['Stripe-Signature' => 'sig_test'])
            ->assertOk();

        $this->assertDatabaseHas('buyer_invoices', [
            'buyer_id' => $buyer->id,
            'stripe_invoice_id' => 'in_finalized_test',
            'pdf_url' => 'https://pay.stripe.com/invoice/pdf/finalized',
        ]);

        Mail::assertQueued(BuyerInvoiceMail::class);
    }

    public function test_invoice_paid_creates_record_transaction_and_email(): void
    {
        Mail::fake();

        $buyer = Buyer::withoutGlobalScopes()->first();
        $account = $buyer->account;
        $this->stripeAccount($account);
        $buyer->update([
            'credit_balance' => 0,
            'stripe_customer_id' => 'cus_paid_invoice',
            'email' => 'paid-invoice@test.com',
        ]);

        $invoice = (object) [
            'id' => 'in_paid_test',
            'status' => 'paid',
            'billing_reason' => 'subscription_cycle',
            'amount_paid' => 4999,
            'amount_due' => 4999,
            'currency' => 'gbp',
            'subscription' => 'sub_test',
            'customer' => 'cus_paid_invoice',
            'invoice_pdf' => 'https://pay.stripe.com/invoice/pdf/paid',
            'metadata' => (object) [],
            'lines' => (object) [
                'data' => [
                    (object) ['price' => (object) ['id' => 'price_monthly']],
                ],
            ],
        ];

        app(StripeCheckoutService::class)->handleInvoicePaid($invoice);

        $this->assertDatabaseHas('buyer_invoices', [
            'buyer_id' => $buyer->id,
            'stripe_invoice_id' => 'in_paid_test',
        ]);

        $this->assertDatabaseHas('buyer_transactions', [
            'buyer_id' => $buyer->id,
            'description' => 'Stripe subscription invoice',
        ]);

        $this->assertSame(500.0, (float) $buyer->fresh()->credit_balance);
        Mail::assertQueued(BuyerInvoiceMail::class);
    }

    public function test_duplicate_stripe_invoice_sync_is_idempotent(): void
    {
        Mail::fake();

        $buyer = Buyer::withoutGlobalScopes()->first();
        $buyer->update([
            'stripe_customer_id' => 'cus_dup_sync',
            'email' => 'dup@test.com',
        ]);

        $stripeInvoice = (object) [
            'id' => 'in_dup_sync',
            'status' => 'open',
            'currency' => 'gbp',
            'amount_due' => 1000,
            'customer' => 'cus_dup_sync',
            'hosted_invoice_url' => 'https://invoice.stripe.com/i/dup',
            'metadata' => (object) [],
        ];

        $service = app(BuyerInvoiceService::class);
        $service->syncFromStripeInvoice($stripeInvoice);
        $queuedAfterFirst = count(Mail::queued(BuyerInvoiceMail::class));
        $this->assertGreaterThan(0, $queuedAfterFirst);

        $service->syncFromStripeInvoice($stripeInvoice);

        $this->assertSame(1, BuyerInvoice::where('stripe_invoice_id', 'in_dup_sync')->count());
        $this->assertSame($queuedAfterFirst, count(Mail::queued(BuyerInvoiceMail::class)));
    }

    public function test_billing_page_includes_invoices(): void
    {
        $account = Account::where('slug', 'excellence-uk')->first();
        $portalUser = User::where('email', 'buyer-portal@excellence-uk.test')->first();
        $buyer = $portalUser->buyer;

        BuyerInvoice::create([
            'buyer_id' => $buyer->id,
            'stripe_invoice_id' => 'in_portal_list',
            'pdf_url' => 'https://pay.stripe.com/invoice/pdf/portal',
            'amount' => 99.50,
            'currency' => 'GBP',
            'status' => 'paid',
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($portalUser)
            ->get(route('portal.buyer.billing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Buyer/Billing')
                ->has('invoices.data', 1)
                ->where('invoices.data.0.stripe_invoice_id', 'in_portal_list')
            );
    }

    public function test_resend_invoice_queues_email(): void
    {
        Mail::fake();

        $portalUser = User::where('email', 'buyer-portal@excellence-uk.test')->first();
        $buyer = $portalUser->buyer;

        $invoice = BuyerInvoice::create([
            'buyer_id' => $buyer->id,
            'stripe_invoice_id' => 'in_resend_test',
            'pdf_url' => 'https://pay.stripe.com/invoice/pdf/resend',
            'amount' => 50,
            'currency' => 'GBP',
            'status' => 'paid',
            'email_sent_at' => now(),
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($portalUser)
            ->post('/portal/buyer/invoices/'.$invoice->id.'/resend')
            ->assertRedirect();

        Mail::assertQueued(BuyerInvoiceMail::class);
    }
}
