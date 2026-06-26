<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Validation\ValidationProviderResolver;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ValidationIntegrationController extends Controller
{
    use ResolvesAdminAccount;

    public function edit(Request $request): Response
    {
        $account = $this->resolveAdminAccount($request);
        $stored = $account->settings['validation_integration'] ?? [];
        $resolver = app(ValidationProviderResolver::class);
        $fraud = app(\App\Services\Billing\FraudProtectionService::class);
        $ipqsUi = $resolver->ipqsSettingsForUi($account);
        $storedIpqs = $stored['ipqs'] ?? [];

        $settings = [
            'enabled' => $stored['enabled'] ?? true,
            'provider' => $stored['provider'] ?? config('validation.driver', 'demo'),
            'email_validation' => $stored['email_validation'] ?? true,
            'hlr_validation' => $stored['hlr_validation'] ?? true,
            'ip_validation' => $stored['ip_validation'] ?? true,
            'url_validation' => $stored['url_validation'] ?? false,
            'quarantine_on_fail' => $stored['quarantine_on_fail'] ?? true,
            'ipqs' => array_merge($ipqsUi, [
                'api_key' => ! empty($storedIpqs['api_key']) ? '••••••••' : '',
            ]),
        ];

        return Inertia::render('Admin/Integrations/Validation', [
            'settings' => $settings,
            'driver' => config('validation.driver'),
            'hasIpqsKey' => filled($resolver->ipqsConfig($account)['api_key'] ?? null),
            'fraudProtection' => $fraud->summary($account),
            'planFeatures' => $this->planFeatures(),
            'demoHints' => [
                'Reject email domains: invalid.demo, bounce.demo, trap.demo',
                'Use +trap in email to simulate spam trap',
                'Phone prefixes 07000, 08000 simulate unreachable HLR',
                'IPs 10.66.x.x and 198.51.100.x simulate high-risk (demo only)',
                'URLs on malware.demo / phish.demo fail in demo mode',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);
        $existing = $account->settings['validation_integration'] ?? [];
        $existingIpqs = $existing['ipqs'] ?? [];
        $fraud = app(\App\Services\Billing\FraudProtectionService::class);

        $request->validate([
            'enabled' => 'boolean',
            'provider' => 'required|in:demo,ipqs',
            'email_validation' => 'boolean',
            'hlr_validation' => 'boolean',
            'ip_validation' => 'boolean',
            'url_validation' => 'boolean',
            'quarantine_on_fail' => 'boolean',
            'ipqs.api_key' => 'nullable|string|max:255',
            'ipqs.fraud_score_threshold' => 'nullable|integer|min:0|max:100',
            'ipqs.url_risk_threshold' => 'nullable|integer|min:0|max:100',
            'ipqs.email_timeout' => 'nullable|integer|min:1|max:60',
            'ipqs.email_abuse_strictness' => 'nullable|integer|min:0|max:2',
            'ipqs.phone_countries' => 'nullable|string|max:120',
            'ipqs.strictness' => 'nullable|integer|min:0|max:3',
            'ipqs.url_strictness' => 'nullable|integer|min:0|max:2',
        ]);

        $ipqs = app(ValidationProviderResolver::class)->ipqsSettingsForUi($account);

        foreach (ValidationProviderResolver::ipqsConfigKeys() as $key) {
            if ($key === 'api_key') {
                continue;
            }

            $default = config("validation.ipqs.{$key}");
            if (is_bool($default)) {
                $ipqs[$key] = $request->boolean("ipqs.{$key}");
            } elseif (is_int($default)) {
                $ipqs[$key] = (int) $request->input("ipqs.{$key}", $existingIpqs[$key] ?? $default);
            } else {
                $ipqs[$key] = $request->input("ipqs.{$key}", $existingIpqs[$key] ?? $default);
            }
        }

        $apiKey = $request->input('ipqs.api_key', '');
        if (! empty($apiKey) && $apiKey !== '••••••••') {
            $ipqs['api_key'] = encrypt($apiKey);
        } elseif (! empty($existingIpqs['api_key'])) {
            $ipqs['api_key'] = $existingIpqs['api_key'];
        }

        $integration = [
            'enabled' => $request->boolean('enabled'),
            'provider' => $fraud->isEntitled($account) ? $request->input('provider') : 'demo',
            'email_validation' => $request->boolean('email_validation'),
            'hlr_validation' => $request->boolean('hlr_validation'),
            'ip_validation' => $request->boolean('ip_validation'),
            'url_validation' => $request->boolean('url_validation') && $fraud->supportsUrlScanner($account),
            'quarantine_on_fail' => $request->boolean('quarantine_on_fail'),
            'ipqs' => $ipqs,
        ];

        $settings = $account->settings ?? [];
        $settings['validation_integration'] = $integration;
        $account->update(['settings' => $settings]);

        return back()->with('success', $fraud->isEntitled($account)
            ? 'Validation & fraud settings saved.'
            : 'Settings saved. Fraud Protection add-on required for live fraud checks.');
    }

    public function test(Request $request): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'ip' => 'nullable|ip',
            'url' => 'nullable|string|max:2048',
            'user_agent' => 'nullable|string|max:512',
        ]);

        $context = new \App\Services\Validation\ValidationContext(
            userAgent: $validated['user_agent'] ?? $request->userAgent(),
        );

        $provider = app(ValidationProviderResolver::class)->forAccount($account);
        $results = [];

        if (! empty($validated['email'])) {
            $r = $provider->validateEmail($validated['email'], $context);
            $results['email'] = ['passed' => $r->passed, 'reason' => $r->reason, 'meta' => $r->meta];
        }

        if (! empty($validated['phone'])) {
            $r = $provider->validateHlr($validated['phone'], $context);
            $results['phone'] = ['passed' => $r->passed, 'reason' => $r->reason, 'meta' => $r->meta];
        }

        if (! empty($validated['ip'])) {
            $r = $provider->validateIp($validated['ip'], $context);
            $results['ip'] = ['passed' => $r->passed, 'reason' => $r->reason, 'meta' => $r->meta];
        }

        if (! empty($validated['url'])) {
            $r = $provider->validateUrl($validated['url'], $context);
            $results['url'] = ['passed' => $r->passed, 'reason' => $r->reason, 'meta' => $r->meta];
        }

        return back()->with('testResults', $results);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function planFeatures(): array
    {
        return [
            [
                'id' => 'email',
                'name' => 'Email Validation',
                'description' => 'Deliverability, disposable, spam trap, leak detection',
                'min_plan' => 'Free',
                'lookups_per_lead' => 1,
            ],
            [
                'id' => 'phone',
                'name' => 'Phone Validation + HLR',
                'description' => 'Validity, carrier, line type, SMS pumping signals',
                'min_plan' => 'Free',
                'lookups_per_lead' => 1,
            ],
            [
                'id' => 'ip',
                'name' => 'IP / Proxy / VPN Detection',
                'description' => 'Fraud score, proxy, VPN, Tor, bot detection',
                'min_plan' => 'Free',
                'lookups_per_lead' => 1,
            ],
            [
                'id' => 'url',
                'name' => 'Malicious URL Scanner',
                'description' => 'Phishing, malware, parked & spam domains',
                'min_plan' => 'Free',
                'lookups_per_lead' => 1,
            ],
            [
                'id' => 'residential_proxy',
                'name' => 'Residential Proxy Detection',
                'description' => 'Enhanced proxy signals (included in IP check)',
                'min_plan' => 'SMB+',
                'lookups_per_lead' => 0,
            ],
            [
                'id' => 'transaction',
                'name' => 'Transaction Scoring',
                'description' => 'Order-level fraud scoring (not wired for lead ingest)',
                'min_plan' => 'SMB Basic',
                'lookups_per_lead' => 0,
                'coming_soon' => true,
            ],
        ];
    }
}
