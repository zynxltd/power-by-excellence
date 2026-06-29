<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StripeIntegrationController extends Controller
{
    use ResolvesAdminAccount;

    public function edit(Request $request): Response
    {
        $account = $this->resolveAdminAccount($request);
        $stripe = $account->settings['stripe'] ?? [];

        return Inertia::render('Admin/Integrations/Stripe', [
            'stripe' => [
                'enabled' => (bool) ($stripe['enabled'] ?? false),
                'allow_buyer_self_serve' => (bool) ($stripe['allow_buyer_self_serve'] ?? true),
                'allow_subscriptions' => (bool) ($stripe['allow_subscriptions'] ?? false),
                'min_topup' => (float) ($stripe['min_topup'] ?? 1),
                'preset_amounts' => $stripe['preset_amounts'] ?? [50, 100, 250, 500, 1000],
                'subscription_prices' => $stripe['subscription_prices'] ?? [],
                'key' => $stripe['key'] ?? config('stripe.key'),
                'secret' => $stripe['secret'] ?? '',
                'webhook_secret' => $stripe['webhook_secret'] ?? '',
            ],
            'webhookUrl' => url('/stripe/webhook'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'enabled' => 'boolean',
            'allow_buyer_self_serve' => 'boolean',
            'allow_subscriptions' => 'boolean',
            'min_topup' => 'nullable|numeric|min:1|max:100000',
            'preset_amounts' => 'nullable|array',
            'preset_amounts.*' => 'numeric|min:1|max:100000',
            'subscription_prices' => 'nullable|array',
            'subscription_prices.*.price_id' => 'required_with:subscription_prices|string|max:255',
            'subscription_prices.*.label' => 'nullable|string|max:255',
            'subscription_prices.*.credit_amount' => 'nullable|numeric|min:0|max:1000000',
            'key' => 'nullable|string|max:255',
            'secret' => 'nullable|string|max:255',
            'webhook_secret' => 'nullable|string|max:255',
        ]);

        $settings = $account->settings ?? [];
        $existing = $settings['stripe'] ?? [];

        $subscriptionPrices = $validated['subscription_prices'] ?? $existing['subscription_prices'] ?? [];
        $subscriptionPrices = array_values(array_filter((array) $subscriptionPrices, fn ($row) => filled($row['price_id'] ?? null)));

        $settings['stripe'] = array_merge($existing, [
            'enabled' => (bool) ($validated['enabled'] ?? false),
            'allow_buyer_self_serve' => (bool) ($validated['allow_buyer_self_serve'] ?? true),
            'allow_subscriptions' => (bool) ($validated['allow_subscriptions'] ?? false),
            'min_topup' => (float) ($validated['min_topup'] ?? $existing['min_topup'] ?? 1),
            'preset_amounts' => array_values($validated['preset_amounts'] ?? $existing['preset_amounts'] ?? [50, 100, 250, 500, 1000]),
            'subscription_prices' => $subscriptionPrices,
            'key' => $validated['key'] ?? $existing['key'] ?? config('stripe.key'),
            'secret' => filled($validated['secret'] ?? null)
                ? $validated['secret']
                : ($existing['secret'] ?? config('stripe.secret')),
            'webhook_secret' => filled($validated['webhook_secret'] ?? null)
                ? $validated['webhook_secret']
                : ($existing['webhook_secret'] ?? config('stripe.webhook_secret')),
        ]);

        $account->update(['settings' => $settings]);

        return back()->with('success', 'Stripe settings saved.');
    }
}
