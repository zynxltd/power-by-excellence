<?php

namespace App\Services\Compliance;

use App\Enums\LawfulBasis;
use App\Models\Campaign;
use App\Models\HostedForm;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FormConsentPolicy
{
    public const CHANNELS = ['email', 'sms', 'phone'];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'require_consent' => false,
            'consent_text' => '',
            'lawful_basis' => LawfulBasis::Consent->value,
            'channel_consent_channels' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forCampaign(Campaign $campaign): array
    {
        $stored = $campaign->validation_config ?? [];

        return self::normalize([
            'require_consent' => $stored['require_consent'] ?? false,
            'consent_text' => $stored['consent_text'] ?? '',
            'lawful_basis' => $stored['lawful_basis'] ?? LawfulBasis::Consent->value,
            'channel_consent_channels' => $stored['channel_consent_channels'] ?? [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function forHostedForm(HostedForm $form): array
    {
        $form->loadMissing('campaign');
        $campaignPolicy = self::forCampaign($form->campaign);
        $formConsent = is_array($form->config['consent'] ?? null) ? $form->config['consent'] : [];

        return self::normalize(array_merge($campaignPolicy, $formConsent));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $basis = (string) ($input['lawful_basis'] ?? LawfulBasis::Consent->value);
        if (! in_array($basis, LawfulBasis::values(), true)) {
            $basis = LawfulBasis::Consent->value;
        }

        $channels = collect($input['channel_consent_channels'] ?? [])
            ->map(fn ($channel) => strtolower(trim((string) $channel)))
            ->filter(fn ($channel) => in_array($channel, self::CHANNELS, true))
            ->unique()
            ->values()
            ->all();

        return [
            'require_consent' => (bool) ($input['require_consent'] ?? false),
            'consent_text' => trim((string) ($input['consent_text'] ?? '')),
            'lawful_basis' => $basis,
            'channel_consent_channels' => $channels,
        ];
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    public static function validateSubmission(array $policy, Request $request): void
    {
        if (! ($policy['require_consent'] ?? false)) {
            return;
        }

        if (! $request->boolean('consent_accepted')) {
            throw ValidationException::withMessages([
                'consent_accepted' => 'You must accept the consent statement to continue.',
            ]);
        }

        if (blank($policy['consent_text'] ?? null)) {
            throw ValidationException::withMessages([
                'consent' => 'Consent is required but no consent text is configured for this form.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public static function buildLeadConsentArtifact(array $policy, Request $request, bool $accepted): array
    {
        $channelConsent = [];
        foreach ($policy['channel_consent_channels'] ?? [] as $channel) {
            $channelConsent[$channel] = $request->boolean("channel_consent.{$channel}")
                || $request->boolean("channel_consent_{$channel}");
        }

        return [
            'consent_text' => (string) ($policy['consent_text'] ?? ''),
            'lawful_basis' => (string) ($policy['lawful_basis'] ?? LawfulBasis::Consent->value),
            'accepted' => $accepted,
            'channel_consent' => $channelConsent,
            'optin_url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function artifactForLead(Lead $lead): ?array
    {
        $artifact = $lead->metadata['consent'] ?? null;

        return is_array($artifact) ? $artifact : null;
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    public static function requiresConsentForLead(array $policy, Lead $lead): bool
    {
        if (! ($policy['require_consent'] ?? false)) {
            return false;
        }

        $artifact = self::artifactForLead($lead);

        return ! ($artifact['accepted'] ?? false) && blank($lead->getField('consent_text'));
    }
}
