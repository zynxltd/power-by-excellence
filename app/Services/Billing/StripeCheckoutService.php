<?php

namespace App\Services\Billing;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\BuyerTransaction;
use App\Models\User;
use Stripe\Checkout\Session;
use Stripe\Stripe;
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
            'metadata' => [
                'buyer_id' => (string) $buyer->id,
                'user_id' => (string) $user->id,
                'account_id' => (string) $buyer->account_id,
            ],
            'success_url' => route('portal.buyer.stripe.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('portal.buyer.billing'),
        ];

        if ($buyer->stripe_customer_id) {
            $params['customer'] = $buyer->stripe_customer_id;
        } else {
            $params['customer_email'] = $user->email;
        }

        return Session::create($params);
    }

    public function handleWebhookCompleted(object $session): ?BuyerTransaction
    {
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

        if (! empty($session->customer) && ! $buyer->stripe_customer_id) {
            $buyer->update(['stripe_customer_id' => $session->customer]);
        }

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

    protected function transactionExists(int $buyerId, string $sessionId): bool
    {
        return BuyerTransaction::query()
            ->where('buyer_id', $buyerId)
            ->where('meta->stripe_session_id', $sessionId)
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
