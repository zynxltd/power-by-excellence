<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Messaging\MessagingCredentialsResolver;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessagingIntegrationController extends Controller
{
    use ResolvesAdminAccount;

    public function edit(Request $request): Response
    {
        $account = $this->resolveAdminAccount($request);
        $messaging = $account->settings['messaging'] ?? [];
        $resolver = app(MessagingCredentialsResolver::class);

        $emailProviders = ['smtp', 'sendgrid', 'mailgun', 'postmark', 'resend'];
        $smsProviders = ['twilio', 'vonage'];

        $providerStatus = [];
        foreach ($emailProviders as $p) {
            $providerStatus[$p] = $resolver->isProviderLive($account, $p, 'email');
        }
        foreach ($smsProviders as $p) {
            $providerStatus[$p] = $resolver->isProviderLive($account, $p, 'sms');
        }

        return Inertia::render('Admin/Integrations/Messaging', [
            'settings' => [
                'email_provider' => $messaging['email_provider'] ?? config('messaging.email_provider', 'smtp'),
                'sms_provider' => $messaging['sms_provider'] ?? config('messaging.sms_provider', 'log'),
                'from_name' => $messaging['from_name'] ?? config('mail.from.name'),
                'from_email' => $messaging['from_email'] ?? config('mail.from.address'),
                'reply_to' => $messaging['reply_to'] ?? null,
                'providers' => $messaging['providers'] ?? [],
            ],
            'providerStatus' => $providerStatus,
            'webhookUrls' => [
                'sendgrid' => url('/webhooks/esp/sendgrid'),
                'mailgun' => url('/webhooks/esp/mailgun'),
                'postmark' => url('/webhooks/esp/postmark'),
            ],
            'emailProviders' => $emailProviders,
            'smsProviders' => $smsProviders,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);
        $existing = $account->settings['messaging'] ?? [];

        $validated = $request->validate([
            'email_provider' => 'required|in:smtp,sendgrid,mailgun,postmark,resend',
            'sms_provider' => 'required|in:log,twilio,vonage',
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:255',
            'reply_to' => 'nullable|email|max:255',
            'providers' => 'nullable|array',
            'providers.sendgrid.key' => 'nullable|string|max:500',
            'providers.mailgun.domain' => 'nullable|string|max:255',
            'providers.mailgun.secret' => 'nullable|string|max:500',
            'providers.postmark.key' => 'nullable|string|max:500',
            'providers.resend.key' => 'nullable|string|max:500',
            'providers.twilio.sid' => 'nullable|string|max:255',
            'providers.twilio.token' => 'nullable|string|max:500',
            'providers.twilio.from' => 'nullable|string|max:32',
            'providers.vonage.key' => 'nullable|string|max:255',
            'providers.vonage.secret' => 'nullable|string|max:500',
            'providers.vonage.from' => 'nullable|string|max:32',
        ]);

        $providers = $existing['providers'] ?? [];

        foreach (['sendgrid', 'mailgun', 'postmark', 'resend', 'twilio', 'vonage'] as $provider) {
            if (! empty($validated['providers'][$provider])) {
                foreach ($validated['providers'][$provider] as $key => $value) {
                    if (filled($value)) {
                        $providers[$provider][$key] = $value;
                    }
                }
            }
        }

        $settings = $account->settings ?? [];
        $settings['messaging'] = [
            'email_provider' => $validated['email_provider'],
            'sms_provider' => $validated['sms_provider'],
            'from_name' => $validated['from_name'] ?? null,
            'from_email' => $validated['from_email'] ?? null,
            'reply_to' => $validated['reply_to'] ?? null,
            'providers' => $providers,
        ];

        $account->update(['settings' => $settings]);

        return back()->with('success', 'Messaging providers updated.');
    }
}
