<?php

namespace App\Services\Validation;

use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Billing\FraudProtectionService;
use App\Services\Logging\PlatformLogger;

class ValidationService
{
    public function __construct(
        protected ValidationProviderResolver $resolver,
        protected FraudProtectionService $fraudProtection,
    ) {}

    public function validateLead(Lead $lead, Campaign $campaign): ?string
    {
        $config = $campaign->validation_config ?? [];
        $account = $campaign->account;
        $accountSettings = $account?->settings ?? [];
        $integration = $accountSettings['validation_integration'] ?? [];
        $enabled = $integration['enabled'] ?? true;

        if (! $enabled || ! $account) {
            return null;
        }

        if (! $this->fraudProtection->isEntitled($account)) {
            return null;
        }

        if (! $this->fraudProtection->canValidateLead($account)) {
            PlatformLogger::leadEvent($lead, 'fraud.cap_exceeded', 'Fraud Protection monthly cap reached — checks skipped for this lead', [
                'usage' => $this->fraudProtection->usageCount($account),
                'cap' => $this->fraudProtection->monthlyCap($account),
            ], 'warning');

            return null;
        }

        $provider = $this->resolver->forAccount($account);
        $context = ValidationContext::fromLead($lead);

        $emailCheck = $config['email_validation'] ?? $integration['email_validation'] ?? true;
        $hlrCheck = $config['hlr_validation'] ?? $integration['hlr_validation'] ?? true;
        $ipCheck = $config['ip_validation'] ?? $integration['ip_validation'] ?? true;
        $urlCheck = ($config['url_validation'] ?? $integration['url_validation'] ?? false)
            && $this->fraudProtection->supportsUrlScanner($account);

        $willRun = ($emailCheck && filled($lead->getField('email')))
            || ($hlrCheck && filled($lead->getField('phone1')))
            || ($ipCheck && filled($lead->ip_address))
            || ($urlCheck && filled($this->resolveLeadUrl($lead)));

        if (! $willRun) {
            return null;
        }

        $this->fraudProtection->recordValidatedLead($account);

        if ($emailCheck) {
            $result = $provider->validateEmail($lead->getField('email'), $context);
            $this->storeResult($lead, 'email_validation', $result);

            if (! $result->passed) {
                return $this->fail($lead, 'email', $result);
            }
        }

        if ($hlrCheck) {
            $result = $provider->validateHlr($lead->getField('phone1'), $context);
            $this->storeResult($lead, 'hlr_validation', $result);

            if (! $result->passed) {
                return $this->fail($lead, 'hlr', $result);
            }
        }

        if ($ipCheck && filled($lead->ip_address)) {
            $result = $provider->validateIp($lead->ip_address, $context);
            $this->storeResult($lead, 'ip_validation', $result);

            if (! $result->passed) {
                return $this->fail($lead, 'ip', $result);
            }
        }

        if ($urlCheck && filled($url = $this->resolveLeadUrl($lead))) {
            $result = $provider->validateUrl($url, $context);
            $this->storeResult($lead, 'url_validation', $result);

            if (! $result->passed) {
                return $this->fail($lead, 'url', $result);
            }
        }

        return null;
    }

    public function shouldQuarantineOnValidationFail(Campaign $campaign): bool
    {
        $config = $campaign->validation_config ?? [];
        $accountSettings = $campaign->account?->settings ?? [];

        return ($config['quarantine_on_validation_fail'] ?? $accountSettings['validation_integration']['quarantine_on_fail'] ?? config('validation.quarantine_on_email_fail', true));
    }

    protected function storeResult(Lead $lead, string $key, ValidationResult $result): void
    {
        $metadata = $lead->metadata ?? [];
        $metadata[$key] = $result->meta + ['passed' => $result->passed];
        $lead->metadata = $metadata;
    }

    protected function fail(Lead $lead, string $stage, ValidationResult $result): string
    {
        PlatformLogger::leadEvent($lead, 'validation.failed', $result->reason, [
            'stage' => $stage,
            'meta' => $result->meta,
        ], 'warning');

        return $result->reason;
    }

    protected function resolveLeadUrl(Lead $lead): ?string
    {
        foreach (['url', 'website', 'landing_url', 'landing_page', 'site_url'] as $field) {
            $value = $lead->getField($field);
            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
