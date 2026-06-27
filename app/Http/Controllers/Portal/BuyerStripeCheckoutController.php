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
                if (($session->payment_status ?? null) === 'paid') {
                    $stripe->handleWebhookCompleted($session);
                }
            } catch (\Throwable) {
                // Webhook may have already credited the account.
            }
        }

        return Inertia::render('Portal/Buyer/StripeSuccess', [
            'buyer' => $buyer->fresh()->only(['id', 'name', 'credit_balance']),
        ]);
    }
}
