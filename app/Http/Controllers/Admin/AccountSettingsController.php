<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\BuyerPortal\BuyerPortalLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountSettingsController extends Controller
{
    use ResolvesAdminAccount;

    public function edit(Request $request): Response
    {
        $account = $this->resolveAdminAccount($request);

        return Inertia::render('Admin/Settings/Edit', [
            'account' => array_merge(
                $account->only(['id', 'name', 'slug', 'timezone', 'default_currency', 'default_country']),
                [
                    'require_buyer_prepay' => $account->settings['require_buyer_prepay'] ?? false,
                    'supplier_iframe_embed' => $account->settings['supplier_iframe_embed'] ?? false,
                    'billing_alert_emails' => $account->settings['billing_alert_emails'] ?? '',
                    'default_low_credit_alert' => $account->settings['default_low_credit_alert'] ?? '',
                    'buyer_portal_locale' => $account->settings['buyer_portal_locale'] ?? BuyerPortalLocale::default(),
                ]
            ),
            'timezones' => timezone_identifiers_list(),
            'currencies' => $this->currencies(),
            'countries' => $this->countries(),
            'buyerPortalLanguages' => BuyerPortalLocale::options(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'timezone' => 'required|timezone:all',
            'default_country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'default_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'require_buyer_prepay' => 'boolean',
            'supplier_iframe_embed' => 'boolean',
            'billing_alert_emails' => 'nullable|string|max:500',
            'default_low_credit_alert' => 'nullable|numeric|min:0',
            'buyer_portal_locale' => 'nullable|string|max:5',
        ], $this->messages());

        $settings = $account->settings ?? [];
        $settings['require_buyer_prepay'] = $validated['require_buyer_prepay'] ?? false;
        $settings['supplier_iframe_embed'] = $validated['supplier_iframe_embed'] ?? false;
        $settings['billing_alert_emails'] = $validated['billing_alert_emails'] ?? '';
        $settings['default_low_credit_alert'] = $validated['default_low_credit_alert'] ?? null;
        $settings['buyer_portal_locale'] = BuyerPortalLocale::isValid($validated['buyer_portal_locale'] ?? null)
            ? $validated['buyer_portal_locale']
            : BuyerPortalLocale::default();

        $account->update([
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'default_country' => $validated['default_country'],
            'default_currency' => $validated['default_currency'],
            'settings' => $settings,
        ]);

        return back()->with('success', 'Platform settings updated.');
    }

    protected function messages(): array
    {
        return [
            'default_country.regex' => 'Country must be a 2-letter ISO code (e.g. GB, US, DE).',
            'default_currency.regex' => 'Currency must be a 3-letter ISO code (e.g. GBP, USD, EUR).',
        ];
    }

    protected function currencies(): array
    {
        return ['GBP', 'USD', 'EUR', 'AUD', 'CAD', 'NZD', 'ZAR', 'INR', 'AED'];
    }

    protected function countries(): array
    {
        return [
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'IE' => 'Ireland',
            'NL' => 'Netherlands',
            'ZA' => 'South Africa',
            'IN' => 'India',
            'AE' => 'United Arab Emirates',
        ];
    }
}
