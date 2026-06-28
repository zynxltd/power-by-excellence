<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class BuyerStripeCheckoutController extends Controller
{
    public function checkout(Request $request, StripeCheckoutService $stripe): RedirectResponse
    {
        $buyer = $request->user()->buyer;
        abort_unless($buyer, 403);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:100000',
        ]);

        abort_unless($stripe->buyerSelfServeEnabled($buyer->account), 422, 'Buyer self-serve top-ups are disabled.');

        $amount = (float) $validated['amount'];
        if ($error = $stripe->validateTopUpAmount($buyer->account, $amount)) {
            return back()->withErrors(['amount' => $error]);
        }

        $session = $stripe->createCheckoutSession(
            $buyer,
            $request->user(),
            $amount,
        );

        return redirect()->away($session->url);
    }

    public function subscribe(Request $request, StripeCheckoutService $stripe): RedirectResponse
    {
        $buyer = $request->user()->buyer;
        abort_unless($buyer, 403);

        $validated = $request->validate([
            'price_id' => 'required|string|max:255',
        ]);

        abort_unless($stripe->subscriptionsEnabled($buyer->account), 422, 'Buyer subscriptions are disabled.');

        if ($error = $stripe->validateSubscriptionPriceId($buyer->account, $validated['price_id'])) {
            return back()->withErrors(['price_id' => $error]);
        }

        try {
            $session = $stripe->createSubscriptionCheckoutSession(
                $buyer,
                $request->user(),
                $validated['price_id'],
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withErrors(['price_id' => $e->getMessage()]);
        }

        return redirect()->away($session->url);
    }

    public function cancelSubscription(Request $request, StripeCheckoutService $stripe): RedirectResponse
    {
        $buyer = $request->user()->buyer;
        abort_unless($buyer, 403);

        abort_unless($stripe->subscriptionsEnabled($buyer->account), 422, 'Buyer subscriptions are disabled.');

        try {
            $stripe->cancelSubscription($buyer);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['subscription' => $e->getMessage()]);
        }

        return back()->with('success', 'Subscription will cancel at the end of the billing period.');
    }

    public function reactivateSubscription(Request $request, StripeCheckoutService $stripe): RedirectResponse
    {
        $buyer = $request->user()->buyer;
        abort_unless($buyer, 403);

        abort_unless($stripe->subscriptionsEnabled($buyer->account), 422, 'Buyer subscriptions are disabled.');

        try {
            $stripe->reactivateSubscription($buyer);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['subscription' => $e->getMessage()]);
        }

        return back()->with('success', 'Subscription reactivated.');
    }

    public function success(Request $request, StripeCheckoutService $stripe): Response|RedirectResponse
    {
        $buyer = $request->user()->buyer;
        abort_unless($buyer, 403);

        $sessionId = $request->string('session_id')->toString();

        if ($sessionId && $stripe->isEnabled($buyer->account)) {
            $settings = $buyer->account?->settings['stripe'] ?? [];
            Stripe::setApiKey($settings['secret'] ?? config('stripe.secret'));

            try {
                $session = Session::retrieve($sessionId);
                if (($session->payment_status ?? null) === 'paid' || ($session->mode ?? null) === 'subscription') {
                    $stripe->handleWebhookCompleted($session);
                }
            } catch (\Throwable) {
                // Webhook may have already processed the session.
            }
        }

        return Inertia::render('Portal/Buyer/StripeSuccess', [
            'buyer' => $buyer->fresh()->only(['id', 'name', 'credit_balance']),
        ]);
    }
}
