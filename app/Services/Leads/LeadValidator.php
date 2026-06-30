<?php

namespace App\Services\Leads;

use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Compliance\FormConsentPolicy;
use App\Services\Logging\PlatformLogger;

class LeadValidator
{
    public function validate(Lead $lead, Campaign $campaign): ?string
    {
        $data = $lead->allFields();

        foreach ($campaign->fields as $field) {
            $value = $lead->getField($field->name);

            if ($field->required && blank($value)) {
                return $this->fail($lead, "Required field missing: {$field->name}");
            }

            if (blank($value)) {
                continue;
            }

            if ($field->name === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $this->fail($lead, 'Invalid email address');
            }

            if (in_array($field->name, ['phone1', 'phone2', 'phone3'], true)) {
                $digits = preg_replace('/\D/', '', (string) $value);
                if (strlen($digits) < 10) {
                    return $this->fail($lead, "Invalid phone: {$field->name}");
                }
            }
        }

        $config = $campaign->validation_config ?? [];

        if (($config['require_email'] ?? false) && blank($lead->getField('email'))) {
            return $this->fail($lead, 'Email is required');
        }

        if (($config['require_phone'] ?? false) && blank($lead->getField('phone1'))) {
            return $this->fail($lead, 'Phone is required');
        }

        if (($config['block_disposable_email'] ?? false)) {
            $email = strtolower((string) $lead->getField('email'));
            $disposable = ['mailinator.com', 'guerrillamail.com', 'tempmail.com', '10minutemail.com', 'yopmail.com'];
            foreach ($disposable as $domain) {
                if (str_ends_with($email, '@'.$domain)) {
                    return $this->fail($lead, 'Disposable email addresses are not allowed');
                }
            }
        }

        if (($config['require_consent'] ?? false)) {
            $artifact = FormConsentPolicy::artifactForLead($lead);
            $accepted = (bool) ($artifact['accepted'] ?? false);
            $hasText = filled($artifact['consent_text'] ?? null) || filled($lead->getField('consent_text'));

            if (! $accepted || ! $hasText) {
                return $this->fail($lead, 'Consent is required');
            }

            $basis = (string) ($artifact['lawful_basis'] ?? $config['lawful_basis'] ?? '');
            if ($basis !== '' && ! in_array($basis, \App\Enums\LawfulBasis::values(), true)) {
                return $this->fail($lead, 'Invalid lawful basis for consent');
            }
        }

        if (! empty($config['custom_rules'])) {
            $engine = app(\App\Services\Rules\RuleEngine::class);
            if (! $engine->matches($config['custom_rules'], $data)) {
                return $this->fail($lead, 'Failed custom validation rules');
            }
        }

        PlatformLogger::leadEvent($lead, 'validation.passed', 'Lead passed field validation');

        return null;
    }

    protected function fail(Lead $lead, string $reason): string
    {
        PlatformLogger::leadEvent($lead, 'validation.failed', $reason, [
            'stage' => 'field',
        ], 'warning');

        return $reason;
    }
}
