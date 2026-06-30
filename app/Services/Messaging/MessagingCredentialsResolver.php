<?php

namespace App\Services\Messaging;

use App\Models\Account;
use App\Models\SendingProfile;

class MessagingCredentialsResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolveForAccount(?Account $account, ?string $provider = null, ?SendingProfile $profile = null): array
    {
        $provider = $provider ?? config('messaging.email_provider', 'smtp');
        $settings = $account?->settings['messaging'] ?? [];
        $providerConfig = $settings['providers'][$provider] ?? [];

        if ($profile) {
            $provider = $profile->provider ?: $provider;
            $providerConfig = array_merge($providerConfig, $profile->config ?? []);
        }

        return [
            'provider' => $provider,
            'from' => $this->resolveFrom($profile, $settings),
            'reply_to' => $this->resolveReplyTo($profile, $settings),
            'credentials' => $this->resolveCredentials($provider, $providerConfig),
        ];
    }

    public function isProviderLive(?Account $account, string $provider, string $channel = 'email'): bool
    {
        $settings = $account?->settings['messaging'] ?? [];
        $providerConfig = $settings['providers'][$provider] ?? [];

        if ($channel === 'sms') {
            return match ($provider) {
                'twilio' => filled($providerConfig['sid'] ?? config('messaging.twilio.sid'))
                    && filled($providerConfig['token'] ?? config('messaging.twilio.token'))
                    && filled($providerConfig['from'] ?? config('messaging.twilio.from')),
                'vonage' => filled($providerConfig['key'] ?? config('messaging.vonage.key'))
                    && filled($providerConfig['secret'] ?? config('messaging.vonage.secret')),
                default => false,
            };
        }

        return match ($provider) {
            'sendgrid' => filled($providerConfig['key'] ?? config('services.sendgrid.key')),
            'mailgun' => filled($providerConfig['domain'] ?? config('services.mailgun.domain'))
                && filled($providerConfig['secret'] ?? config('services.mailgun.secret')),
            'postmark' => filled($providerConfig['key'] ?? config('services.postmark.key')),
            'resend' => filled($providerConfig['key'] ?? config('services.resend.key')),
            default => filled(config('mail.mailers.smtp.host')) || config('mail.default') === 'log',
        };
    }

    public function resolveSendingProfile(int $accountId, ?int $profileId, ?string $recipientEmail = null): ?SendingProfile
    {
        if ($profileId) {
            return SendingProfile::query()->where('account_id', $accountId)->find($profileId);
        }

        if ($recipientEmail && str_contains($recipientEmail, '@')) {
            $domain = strtolower(substr(strrchr($recipientEmail, '@'), 1) ?: '');

            if ($domain) {
                $matched = SendingProfile::query()
                    ->where('account_id', $accountId)
                    ->whereNotNull('domain_match')
                    ->get()
                    ->first(fn (SendingProfile $p) => $this->domainMatches($domain, (string) $p->domain_match));

                if ($matched) {
                    return $matched;
                }
            }
        }

        return SendingProfile::query()
            ->where('account_id', $accountId)
            ->where('is_default', true)
            ->first();
    }

    /**
     * @return array{spf: string, dkim: string, return_path: string}|array{}
     */
    public function dnsHintsForSendingDomain(?string $domain): array
    {
        $domain = strtolower(trim((string) $domain));

        if ($domain === '') {
            return [];
        }

        return [
            'spf' => "TXT @{$domain}: v=spf1 include:sendgrid.net ~all (adjust for your ESP)",
            'dkim' => "CNAME s1._domainkey.{$domain} → your ESP DKIM target (e.g. SendGrid/Mailgun)",
            'return_path' => "CNAME em.{$domain} or bounce.{$domain} for return-path alignment",
        ];
    }

    protected function domainMatches(string $domain, string $pattern): bool
    {
        $pattern = strtolower(trim($pattern));

        if ($pattern === '' || $pattern === '*') {
            return true;
        }

        if (str_starts_with($pattern, '*.')) {
            $suffix = substr($pattern, 2);

            return $domain === $suffix || str_ends_with($domain, '.'.$suffix);
        }

        return $domain === $pattern;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function resolveFrom(?SendingProfile $profile, array $settings): ?string
    {
        if ($profile) {
            $email = $this->resolveProfileFromEmail($profile, $settings);

            if ($email) {
                $name = $profile->from_name ?? ($settings['from_name'] ?? null);

                return $name ? "{$name} <{$email}>" : $email;
            }
        }

        $fromEmail = $settings['from_email'] ?? null;
        $fromName = $settings['from_name'] ?? null;

        if ($fromEmail && $fromName) {
            return "{$fromName} <{$fromEmail}>";
        }

        return $fromEmail ?? config('mail.from.address');
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function resolveReplyTo(?SendingProfile $profile, array $settings): ?string
    {
        if ($profile?->reply_to) {
            return $profile->reply_to;
        }

        if ($profile?->sending_domain) {
            return $this->buildAddressFromSendingDomain($profile, $settings);
        }

        return $settings['reply_to'] ?? null;
    }

    protected function resolveProfileFromEmail(SendingProfile $profile, array $settings): ?string
    {
        if ($profile->sending_domain) {
            return $this->buildAddressFromSendingDomain($profile, $settings);
        }

        return $profile->from_email;
    }

    protected function buildAddressFromSendingDomain(SendingProfile $profile, array $settings, string $defaultLocal = 'noreply'): string
    {
        $domain = strtolower(trim((string) $profile->sending_domain));
        $local = $defaultLocal;

        if ($profile->from_email && str_contains($profile->from_email, '@')) {
            $local = strstr($profile->from_email, '@', true) ?: $defaultLocal;
        } elseif (! empty($settings['from_email']) && str_contains($settings['from_email'], '@')) {
            $local = strstr($settings['from_email'], '@', true) ?: $defaultLocal;
        }

        return "{$local}@{$domain}";
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     * @return array<string, mixed>
     */
    protected function resolveCredentials(string $provider, array $providerConfig): array
    {
        return match ($provider) {
            'sendgrid' => ['key' => $providerConfig['key'] ?? config('services.sendgrid.key')],
            'mailgun' => [
                'domain' => $providerConfig['domain'] ?? config('services.mailgun.domain'),
                'secret' => $providerConfig['secret'] ?? config('services.mailgun.secret'),
            ],
            'postmark' => ['key' => $providerConfig['key'] ?? config('services.postmark.key')],
            'resend' => ['key' => $providerConfig['key'] ?? config('services.resend.key')],
            'twilio' => [
                'sid' => $providerConfig['sid'] ?? config('messaging.twilio.sid'),
                'token' => $providerConfig['token'] ?? config('messaging.twilio.token'),
                'from' => $providerConfig['from'] ?? config('messaging.twilio.from'),
            ],
            'vonage' => [
                'key' => $providerConfig['key'] ?? config('messaging.vonage.key'),
                'secret' => $providerConfig['secret'] ?? config('messaging.vonage.secret'),
                'from' => $providerConfig['from'] ?? config('messaging.vonage.from'),
            ],
            default => [],
        };
    }
}
