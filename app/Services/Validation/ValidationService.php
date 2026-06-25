<?php

namespace App\Services\Validation;

use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Logging\PlatformLogger;

class ValidationService
{
    public function __construct(
        protected DemoValidationProvider $provider,
    ) {}

    public function validateLead(Lead $lead, Campaign $campaign): ?string
    {
        $config = $campaign->validation_config ?? [];
        $accountSettings = $campaign->account?->settings ?? [];
        $integration = $accountSettings['validation_integration'] ?? [];
        $enabled = $integration['enabled'] ?? true;

        if (! $enabled) {
            return null;
        }

        $emailCheck = $config['email_validation'] ?? $integration['email_validation'] ?? true;
        $hlrCheck = $config['hlr_validation'] ?? $integration['hlr_validation'] ?? true;

        if ($emailCheck) {
            $result = $this->provider->validateEmail($lead->getField('email'));
            $metadata = $lead->metadata ?? [];
            $metadata['email_validation'] = $result->meta + ['passed' => $result->passed];
            $lead->metadata = $metadata;

            if (! $result->passed) {
                PlatformLogger::leadEvent($lead, 'validation.failed', $result->reason, [
                    'stage' => 'email',
                    'meta' => $result->meta,
                ], 'warning');

                return $result->reason;
            }
        }

        if ($hlrCheck) {
            $result = $this->provider->validateHlr($lead->getField('phone1'));
            $metadata = $lead->metadata ?? [];
            $metadata['hlr_validation'] = $result->meta + ['passed' => $result->passed];
            $lead->metadata = $metadata;

            if (! $result->passed) {
                PlatformLogger::leadEvent($lead, 'validation.failed', $result->reason, [
                    'stage' => 'hlr',
                    'meta' => $result->meta,
                ], 'warning');

                return $result->reason;
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
}
