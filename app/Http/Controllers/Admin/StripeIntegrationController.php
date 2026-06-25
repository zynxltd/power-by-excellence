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
        $stripe = $account->settings['stripe'] ?? [
            'enabled' => false,
            'mode' => 'test',
            'publishable_key' => '',
            'secret_key' => '',
            'webhook_secret' => '',
            'buyer_self_serve_topup' => true,
        ];

        return Inertia::render('Admin/Integrations/Stripe', [
            'stripe' => [
                'enabled' => (bool) ($stripe['enabled'] ?? false),
                'mode' => $stripe['mode'] ?? 'test',
                'publishable_key' => $stripe['publishable_key'] ?? '',
                'secret_key' => $stripe['secret_key'] ? '••••••••' : '',
                'webhook_secret' => $stripe['webhook_secret'] ? '••••••••' : '',
                'buyer_self_serve_topup' => (bool) ($stripe['buyer_self_serve_topup'] ?? true),
            ],
            'webhookUrl' => url('/api/v1/integrations/stripe/webhook'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);
        $existing = $account->settings['stripe'] ?? [];

        $validated = $request->validate([
            'enabled' => 'boolean',
            'mode' => 'required|in:test,live',
            'publishable_key' => 'nullable|string|max:255',
            'secret_key' => 'nullable|string|max:255',
            'webhook_secret' => 'nullable|string|max:255',
            'buyer_self_serve_topup' => 'boolean',
        ]);

        $stripe = array_merge($existing, [
            'enabled' => (bool) ($validated['enabled'] ?? false),
            'mode' => $validated['mode'],
            'publishable_key' => $validated['publishable_key'] ?? '',
            'buyer_self_serve_topup' => (bool) ($validated['buyer_self_serve_topup'] ?? true),
        ]);

        if (! empty($validated['secret_key']) && $validated['secret_key'] !== '••••••••') {
            $stripe['secret_key'] = encrypt($validated['secret_key']);
        }

        if (! empty($validated['webhook_secret']) && $validated['webhook_secret'] !== '••••••••') {
            $stripe['webhook_secret'] = encrypt($validated['webhook_secret']);
        }

        $settings = $account->settings ?? [];
        $settings['stripe'] = $stripe;
        $account->update(['settings' => $settings]);

        return back()->with('success', 'Stripe settings saved.');
    }
}
