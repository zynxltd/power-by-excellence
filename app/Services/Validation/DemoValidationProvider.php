<?php

namespace App\Services\Validation;

class DemoValidationProvider
{
    public function validateEmail(?string $email): ValidationResult
    {
        if (blank($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ValidationResult::fail('Invalid email format', ['check' => 'syntax']);
        }

        $domain = strtolower(substr(strrchr($email, '@'), 1) ?: '');
        $reject = config('validation.demo.reject_domains', []);

        if (in_array($domain, $reject, true)) {
            return ValidationResult::fail('Email domain failed deliverability check', [
                'check' => 'smtp',
                'domain' => $domain,
                'status' => 'undeliverable',
            ]);
        }

        if (str_contains($email, '+trap')) {
            return ValidationResult::fail('Email flagged as spam trap', [
                'check' => 'reputation',
                'status' => 'trap',
            ]);
        }

        return ValidationResult::pass([
            'check' => 'smtp',
            'domain' => $domain,
            'status' => 'deliverable',
            'duration_ms' => random_int(12, 85),
        ]);
    }

    public function validateHlr(?string $phone): ValidationResult
    {
        if (blank($phone)) {
            return ValidationResult::fail('Phone number missing', ['check' => 'hlr']);
        }

        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) < 10) {
            return ValidationResult::fail('Invalid phone number length', ['check' => 'hlr']);
        }

        foreach (config('validation.demo.hlr_unreachable_prefixes', []) as $prefix) {
            if (str_starts_with($digits, $prefix)) {
                return ValidationResult::fail('Mobile number not reachable (HLR)', [
                    'check' => 'hlr',
                    'status' => 'unreachable',
                    'network' => 'unknown',
                ]);
            }
        }

        $networks = ['EE', 'Vodafone', 'O2', 'Three', 'AT&T', 'Verizon', 'T-Mobile'];
        $statuses = ['active', 'active', 'active', 'roaming'];

        return ValidationResult::pass([
            'check' => 'hlr',
            'status' => $statuses[array_rand($statuses)],
            'network' => $networks[array_rand($networks)],
            'duration_ms' => random_int(45, 320),
        ]);
    }
}
