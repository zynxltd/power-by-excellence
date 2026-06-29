<?php

namespace App\Services\Billing;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\BuyerTransaction;
use App\Models\User;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Webhook;

class StripeCheckoutService
{
    public function isEnabled(?Account $account): bool
    {
        return $this->hasCredentials($account) && ($account?->settings['stripe']['enabled'] ?? false);
    }

    public function buyerSelfServeEnabled(?Account $account): bool
    {
        if (! $this->isEnabled($account)) {
            return false;
        }

        return (bool) ($account?->settings['stripe']['allow_buyer_self_serve'] ?? true);
    }

    public function subscriptionsEnabled(?Account $account): bool
    {
        if (! $this->buyerSelfServeEnabled($account)) {
            return false;
        }

        if (! ($account?->settings['stripe']['allow_subscriptions'] ?? false)) {
            return false;
        }

        return count($this->subscriptionPlans($account)) > 0;
    }

    /**
     * @return list<array{price_id: string, label: string, credit_amount: ?float}>
     */
    public function subscriptionPlans(?Account $account): array
    {
        $rows = $account?->settings['stripe']['subscription_prices'] ?? [];

        $plans = [];
        foreach ((array) $rows as $row) {
            $priceId = trim((string) ($row['price_id'] ?? ''));
            if ($priceId === '') {
                continue;
            }

            $plans[] = [
                'price_id' => $priceId,
                'label' => filled($row['label'] ?? null) ? (string) $row['label'] : $priceId,
                'credit_amount' => isset($row['credit_amount']) ? (float) $row['credit_amount'] : null,
            ];
        }

        return $plans;
    }

    public function validateSubscriptionPriceId(?Account $account, string $priceId): ?string
    {
        $allowed = array_column($this->subscriptionPlans($account), 'price_id');

        if (! in_array($priceId, $allowed, true)) {
            return 'Invalid subscription plan.';
        }

        return null;
    }

    /**
     * @return list<int|float>
     */
    public function presetAmounts(?Account $account): array
    {
        $presets = $account?->settings['stripe']['preset_amounts'] ?? [50, 100, 250, 500, 1000];

        return array_values(array_filter(array_map('floatval', (array) $presets), fn ($amount) => $amount >= 1));
    }

    public function minimumTopUp(?Account $account): float
    {
        return max(1.0, (float) ($account?->settings['stripe']['min_topup'] ?? 1));
    }

    public function validateTopUpAmount(?Account $account, float $amount): ?string
    {
        $min = $this->minimumTopUp($account);

        if ($amount < $min) {
            return "Minimum top-up is {$min}.";
        }

        if ($amount > 100000) {
            return 'Maximum top-up is 100000.';
        }

        return null;
    }

    /**
     * @return array{
     *     id: string,
     *     status: string,
     *     cancel_at_period_end: bool,
     *     current_period_end: ?int,
     *     price_id: ?string,
     *     label: ?string,
     *     is_active: bool
     * }|null
     */
    public function subscriptionStatus(Buyer $buyer): ?array
    {
        $sub = $buyer->settings['stripe_subscription'] ?? null;
        if (! is_array($sub) || empty($sub['id'])) {
            return null;
        }

        $planLabel = null;
        foreach ($this->subscriptionPlans($buyer->account) as $plan) {
            if ($plan['price_id'] === ($sub['price_id'] ?? null)) {
                $planLabel = $plan['label'];
                break;
            }
        }

        $status = (string) ($sub['status'] ?? 'unknown');

        return [
            'id' => (string) $sub['id'],
            'status' => $status,
            'cancel_at_period_end' => (bool) ($sub['cancel_at_period_end'] ?? false),
            'current_period_end' => isset($sub['current_period_end']) ? (int) $sub['current_period_end'] : null,
            'price_id' => isset($sub['price_id']) ? (string) $sub['price_id'] : null,
            'label' => $planLabel,
            'is_active' => in_array($status, ['active', 'trialing'], true),
        ];
    }

    public function createCheckoutSession(Buyer $buyer, User $user, float $amount): Session
    {
        if ($error = $this->validateTopUpAmount($buyer->account, $amount)) {
            throw new \InvalidArgumentException($error);
        }

        if (! $this->buyerSelfServeEnabled($buyer->account)) {
            throw new \RuntimeException('Buyer self-serve top-ups are disabled.');
        }

        $this->configureStripe($buyer->account);

        $currency = strtolower($buyer->resolvedCurrency());
        $amountCents = (int) round($amount * 100);

        $params = [
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => 'Buyer credit top-up',
                        'description' => "Credit for {$buyer->name}",
                    ],
                    'unit_amount' => $amountCents,
                ],
                'quantity' => 1,
            ]],
            'metadata' => $this->checkoutMetadata($buyer, $user),
            'success_url' => route('portal.buyer.stripe.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('portal.buyer.billing'),
        ];

        $this->applyCustomerParams($params, $buyer, $user);

        return Session::create($params);
    }

    public function createSubscriptionCheckoutSession(Buyer $buyer, User $user, string $priceId): Session
    {
        if ($error = $this->validateSubscriptionPriceId($buyer->account, $priceId)) {
            throw new \InvalidArgumentException($error);
        }

        if (! $this->subscriptionsEnabled($buyer->account)) {
            throw new \RuntimeException('Buyer subscriptions are disabled.');
        }

        $existing = $this->subscriptionStatus($buyer);
        if ($existing && $existing['is_active']) {
            throw new \RuntimeException('An active subscription already exists.');
        }

        $this->configureStripe($buyer->account);

        $metadata = $this->checkoutMetadata($buyer, $user);

        $params = [
            'mode' => 'subscription',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'metadata' => $metadata,
            'subscription_data' => [
                'metadata' => $metadata,
            ],
            'success_url' => route('portal.buyer.stripe.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('portal.buyer.billing'),
        ];

        $this->applyCustomerParams($params, $buyer, $user);

        return Session::create($params);
    }

    public function cancelSubscription(Buyer $buyer): void
    {
        $subscriptionId = $this->activeSubscriptionId($buyer);
        if (! $subscriptionId) {
            throw new \RuntimeException('No active subscription.');
        }

        $this->configureStripe($buyer->account);
        $updated = Subscription::update($subscriptionId, ['cancel_at_period_end' => true]);
        $this->syncBuyerSubscription($buyer, $updated);
    }

    public function reactivateSubscription(Buyer $buyer): void
    {
        $status = $this->subscriptionStatus($buyer);
        if (! $status || ! $status['is_active'] || ! $status['cancel_at_period_end']) {
            throw new \RuntimeException('No subscription scheduled for cancellation.');
        }

        $this->configureStripe($buyer->account);
        $updated = Subscription::update($status['id'], ['cancel_at_period_end' => false]);
        $this->syncBuyerSubscription($buyer, $updated);
    }

    public function handleWebhookCompleted(object $session): ?BuyerTransaction
    {
        if (($session->mode ?? 'payment') === 'subscription') {
            $this->handleSubscriptionCheckoutCompleted($session);

            return null;
        }

        $sessionId = $session->id ?? null;
        $buyerId = (int) ($session->metadata->buyer_id ?? 0);
        $amountTotal = (float) (($session->amount_total ?? 0) / 100);

        if (! $sessionId || ! $buyerId || $amountTotal <= 0) {
            return null;
        }

        if (($session->payment_status ?? 'paid') !== 'paid') {
            return null;
        }

        $buyer = Buyer::withoutGlobalScopes()->find($buyerId);
        if (! $buyer) {
            return null;
        }

        if ($this->transactionExists($buyerId, $sessionId)) {
            return BuyerTransaction::query()
                ->where('buyer_id', $buyerId)
                ->where('meta->stripe_session_id', $sessionId)
                ->first();
        }

        $this->syncStripeCustomer($buyer, $session->customer ?? null);

        return app(BuyerBillingService::class)->credit(
            $buyer,
            $amountTotal,
            'Stripe checkout top-up',
            [
                'meta' => ['stripe_session_id' => $sessionId],
                'bypass_account_lock' => true,
            ],
        );
    }

    public function handleInvoicePaid(object $invoice): ?BuyerTransaction
    {
        $invoiceId = $invoice->id ?? null;
        if (! $invoiceId) {
            return null;
        }

        $billingReason = $invoice->billing_reason ?? null;
        if (! in_array($billingReason, ['subscription_create', 'subscription_cycle', 'subscription_update'], true)) {
            return null;
        }

        if (($invoice->status ?? '') !== 'paid') {
            return null;
        }

        $buyer = $this->resolveBuyerFromStripeObject($invoice);
        if (! $buyer) {
            return null;
        }

        if ($this->invoiceTransactionExists($buyer->id, $invoiceId)) {
            return BuyerTransaction::query()
                ->where('buyer_id', $buyer->id)
                ->where('meta->stripe_invoice_id', $invoiceId)
                ->first();
        }

        $amount = $this->creditAmountForInvoice($buyer, $invoice);
        if ($amount <= 0) {
            return null;
        }

        return app(BuyerBillingService::class)->credit(
            $buyer,
            $amount,
            'Stripe subscription invoice',
            [
                'meta' => [
                    'stripe_invoice_id' => $invoiceId,
                    'stripe_subscription_id' => $invoice->subscription ?? null,
                ],
                'bypass_account_lock' => true,
            ],
        );
    }

    public function handleSubscriptionUpdated(object $subscription): void
    {
        $buyer = $this->resolveBuyerFromStripeObject($subscription);
        if (! $buyer) {
            return;
        }

        $this->syncBuyerSubscription($buyer, $subscription);
    }

    public function handleSubscriptionDeleted(object $subscription): void
    {
        $buyer = $this->resolveBuyerFromStripeObject($subscription);
        if (! $buyer) {
            return;
        }

        $storedId = $buyer->settings['stripe_subscription']['id'] ?? null;
        if ($storedId && $storedId !== ($subscription->id ?? null)) {
            return;
        }

        $this->clearBuyerSubscription($buyer);
    }

    public function constructWebhookEvent(string $payload, ?string $signature): object
    {
        $account = request()->attributes->get('account');
        $secret = $account?->settings['stripe']['webhook_secret']
            ?? config('stripe.webhook_secret');

        if ($secret && $signature) {
            return Webhook::constructEvent($payload, $signature, $secret);
        }

        throw new \InvalidArgumentException('Stripe webhook signature verification failed.');
    }

    protected function handleSubscriptionCheckoutCompleted(object $session): void
    {
        $buyerId = (int) ($session->metadata->buyer_id ?? 0);
        if (! $buyerId) {
            return;
        }

        $buyer = Buyer::withoutGlobalScopes()->find($buyerId);
        if (! $buyer) {
            return;
        }

        $this->syncStripeCustomer($buyer, $session->customer ?? null);

        if (empty($session->subscription)) {
            return;
        }

        $settings = $buyer->settings ?? [];
        $settings['stripe_subscription'] = array_merge($settings['stripe_subscription'] ?? [], [
            'id' => (string) $session->subscription,
            'status' => 'active',
            'cancel_at_period_end' => false,
        ]);
        $buyer->update(['settings' => $settings]);
    }

    protected function creditAmountForInvoice(Buyer $buyer, object $invoice): float
    {
        $priceId = $this->priceIdFromInvoice($invoice);

        if ($priceId) {
            foreach ($this->subscriptionPlans($buyer->account) as $plan) {
                if ($plan['price_id'] === $priceId && ($plan['credit_amount'] ?? 0) > 0) {
                    return (float) $plan['credit_amount'];
                }
            }
        }

        return (float) (($invoice->amount_paid ?? 0) / 100);
    }

    protected function priceIdFromInvoice(object $invoice): ?string
    {
        $lines = $invoice->lines->data ?? $invoice->lines ?? [];
        $first = is_array($lines) ? ($lines[0] ?? null) : null;

        if (! $first) {
            return null;
        }

        return $first->price->id ?? $first->plan->id ?? null;
    }

    protected function resolveBuyerFromStripeObject(object $object): ?Buyer
    {
        $buyerId = (int) ($object->metadata->buyer_id ?? 0);
        if ($buyerId) {
            return Buyer::withoutGlobalScopes()->find($buyerId);
        }

        return $this->findBuyerByStripeCustomer($object->customer ?? null);
    }

    protected function findBuyerByStripeCustomer(?string $customerId): ?Buyer
    {
        if (! $customerId) {
            return null;
        }

        return Buyer::withoutGlobalScopes()
            ->where('stripe_customer_id', $customerId)
            ->first();
    }

    protected function syncBuyerSubscription(Buyer $buyer, object $subscription): void
    {
        $items = $subscription->items->data ?? [];
        $firstItem = is_array($items) ? ($items[0] ?? null) : null;
        $priceId = $firstItem->price->id ?? $firstItem->plan->id ?? null;

        $settings = $buyer->settings ?? [];
        $settings['stripe_subscription'] = [
            'id' => (string) ($subscription->id ?? ''),
            'status' => (string) ($subscription->status ?? 'unknown'),
            'cancel_at_period_end' => (bool) ($subscription->cancel_at_period_end ?? false),
            'current_period_end' => $subscription->current_period_end ?? null,
            'price_id' => $priceId ? (string) $priceId : null,
        ];

        if (! empty($subscription->customer)) {
            $buyer->stripe_customer_id = (string) $subscription->customer;
        }

        $buyer->update([
            'settings' => $settings,
            'stripe_customer_id' => $buyer->stripe_customer_id,
        ]);
    }

    protected function clearBuyerSubscription(Buyer $buyer): void
    {
        $settings = $buyer->settings ?? [];
        unset($settings['stripe_subscription']);
        $buyer->update(['settings' => $settings]);
    }

    protected function activeSubscriptionId(Buyer $buyer): ?string
    {
        $status = $this->subscriptionStatus($buyer);

        return ($status && $status['is_active']) ? $status['id'] : null;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function applyCustomerParams(array &$params, Buyer $buyer, User $user): void
    {
        if ($buyer->stripe_customer_id) {
            $params['customer'] = $buyer->stripe_customer_id;
        } else {
            $params['customer_email'] = $user->email;
        }
    }

    /**
     * @return array<string, string>
     */
    protected function checkoutMetadata(Buyer $buyer, User $user): array
    {
        return [
            'buyer_id' => (string) $buyer->id,
            'user_id' => (string) $user->id,
            'account_id' => (string) $buyer->account_id,
        ];
    }

    protected function syncStripeCustomer(Buyer $buyer, ?string $customerId): void
    {
        if ($customerId && ! $buyer->stripe_customer_id) {
            $buyer->update(['stripe_customer_id' => $customerId]);
        }
    }

    protected function transactionExists(int $buyerId, string $sessionId): bool
    {
        return BuyerTransaction::query()
            ->where('buyer_id', $buyerId)
            ->where('meta->stripe_session_id', $sessionId)
            ->exists();
    }

    protected function invoiceTransactionExists(int $buyerId, string $invoiceId): bool
    {
        return BuyerTransaction::query()
            ->where('buyer_id', $buyerId)
            ->where('meta->stripe_invoice_id', $invoiceId)
            ->exists();
    }

    protected function hasCredentials(?Account $account): bool
    {
        $settings = $account?->settings['stripe'] ?? [];

        return filled($settings['secret'] ?? config('stripe.secret'))
            && filled($settings['key'] ?? config('stripe.key'));
    }

    protected function configureStripe(?Account $account): void
    {
        $settings = $account?->settings['stripe'] ?? [];
        Stripe::setApiKey($settings['secret'] ?? config('stripe.secret'));
    }
}
