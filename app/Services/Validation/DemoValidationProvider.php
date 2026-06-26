<?php

namespace App\Services\Validation;

use App\Services\Validation\Contracts\ValidationProvider;

class DemoValidationProvider implements ValidationProvider
{
    public function validateEmail(?string $email, ?ValidationContext $context = null): ValidationResult
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

    public function validateHlr(?string $phone, ?ValidationContext $context = null): ValidationResult
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

    public function validateIp(?string $ip, ?ValidationContext $context = null): ValidationResult
    {
        if (blank($ip) || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return ValidationResult::fail('IP address missing or invalid', ['provider' => 'demo', 'check' => 'ip']);
        }

        $whitelist = IpWhitelistMatcher::whitelistFromContext($context);
        if (IpWhitelistMatcher::isWhitelisted($ip, $whitelist)) {
            return ValidationResult::pass([
                'provider' => 'demo',
                'check' => 'ip',
                'status' => 'whitelisted',
                'whitelisted' => true,
            ]);
        }

        foreach (config('validation.demo.high_risk_ip_prefixes', ['10.66.', '198.51.100.']) as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                return ValidationResult::fail('High-risk IP range (demo)', [
                    'provider' => 'demo',
                    'check' => 'ip',
                    'fraud_score' => 95,
                    'status' => 'high_risk',
                ]);
            }
        }

        return ValidationResult::pass([
            'provider' => 'demo',
            'check' => 'ip',
            'fraud_score' => random_int(5, 25),
            'status' => 'clean',
        ]);
    }

    public function validateUrl(?string $url, ?ValidationContext $context = null): ValidationResult
    {
        if (blank($url)) {
            return ValidationResult::fail('URL missing', ['provider' => 'demo', 'check' => 'url']);
        }

        $host = parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST) ?: $url;

        foreach (config('validation.demo.malicious_url_hosts', []) as $bad) {
            if (str_contains(strtolower($host), strtolower($bad))) {
                return ValidationResult::fail('Malicious URL (demo)', [
                    'provider' => 'demo',
                    'check' => 'url',
                    'risk_score' => 95,
                    'status' => 'high_risk',
                ]);
            }
        }

        return ValidationResult::pass([
            'provider' => 'demo',
            'check' => 'url',
            'risk_score' => random_int(0, 20),
            'domain' => $host,
            'status' => 'clean',
        ]);
    }
}
