<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\PlatformFeatureParity\PortalDomain;
use App\PlatformFeatureParity\PortalDomainVerification;
use App\Services\Compliance\DataRetentionPolicy;
use App\Services\Security\AdminIpAllowlistPolicy;
use App\Services\Security\TwoFactorService;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\BuyerPortal\BuyerPortalLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountSettingsController extends Controller
{
    use ResolvesAdminAccount;

    public function __construct(
        protected PortalDomainVerification $portalDomainVerification,
    ) {}

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
                    'custom_portal_domain' => $account->settings['custom_portal_domain'] ?? '',
                    'require_2fa_for_staff' => $account->settings['require_2fa_for_staff'] ?? false,
                    'require_2fa_for_portal' => $account->settings['require_2fa_for_portal'] ?? false,
                    'two_factor_grace_days' => (int) ($account->settings['two_factor_grace_days'] ?? 7),
                    'data_retention' => DataRetentionPolicy::forInertia($account),
                    'security' => AdminIpAllowlistPolicy::forInertia($account),
                ]
            ),
            'clientIp' => $request->ip(),
            'portalDomain' => $this->portalDomainVerification->statusForAccount($account),
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
            'custom_portal_domain' => 'nullable|string|max:255',
            'require_2fa_for_staff' => 'boolean',
            'require_2fa_for_portal' => 'boolean',
            'two_factor_grace_days' => 'nullable|integer|min:0|max:90',
            'data_retention' => 'nullable|array',
            'data_retention.purge_leads' => 'nullable|boolean',
            'data_retention.leads_retention_days' => 'nullable|integer|min:30|max:3650',
            'data_retention.purge_logs' => 'nullable|boolean',
            'data_retention.logs_retention_days' => 'nullable|integer|min:7|max:3650',
            'data_retention.purge_message_events' => 'nullable|boolean',
            'data_retention.message_events_retention_days' => 'nullable|integer|min:7|max:3650',
            'security' => 'nullable|array',
            'security.admin_ip_allowlist_enabled' => 'boolean',
            'security.admin_ip_allowlist_text' => 'nullable|string|max:5000',
            'security.admin_geo_block_enabled' => 'boolean',
            'security.blocked_country_codes_text' => 'nullable|string|max:500',
        ], $this->messages());

        $settings = $account->settings ?? [];
        $previousCustomDomain = PortalDomain::customHost($account);
        $settings['require_buyer_prepay'] = $validated['require_buyer_prepay'] ?? false;
        $settings['supplier_iframe_embed'] = $validated['supplier_iframe_embed'] ?? false;
        $settings['billing_alert_emails'] = $validated['billing_alert_emails'] ?? '';
        $settings['default_low_credit_alert'] = $validated['default_low_credit_alert'] ?? null;
        $settings['buyer_portal_locale'] = BuyerPortalLocale::isValid($validated['buyer_portal_locale'] ?? null)
            ? $validated['buyer_portal_locale']
            : BuyerPortalLocale::default();
        $settings['custom_portal_domain'] = filled($validated['custom_portal_domain'] ?? null)
            ? strtolower(trim((string) $validated['custom_portal_domain']))
            : null;

        $nextCustomDomain = PortalDomain::normalize($settings['custom_portal_domain'] ?? null);

        if ($previousCustomDomain !== $nextCustomDomain) {
            unset(
                $settings['custom_portal_domain_verified_at'],
                $settings['custom_portal_domain_verification_token'],
            );
        }

        $settings = app(TwoFactorService::class)->mergePolicySettings($settings, $validated);

        if (array_key_exists('data_retention', $validated)) {
            $settings[DataRetentionPolicy::SETTINGS_KEY] = DataRetentionPolicy::normalize(
                $validated['data_retention'] ?? []
            );
        }

        if (array_key_exists('security', $validated)) {
            $settings[AdminIpAllowlistPolicy::SETTINGS_KEY] = AdminIpAllowlistPolicy::normalizeInput(
                $validated['security'] ?? []
            );
        }

        $account->update([
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'default_country' => $validated['default_country'],
            'default_currency' => $validated['default_currency'],
            'settings' => $settings,
        ]);

        return back()->with('success', 'Platform settings updated.');
    }

    public function verifyPortalDomain(Request $request): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);
        $result = $this->portalDomainVerification->verify($account);

        if ($result['verified']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
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
