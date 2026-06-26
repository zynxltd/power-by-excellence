<?php

namespace App\Services\Validation;

use App\Services\Validation\Contracts\ValidationProvider;
use Illuminate\Support\Facades\Http;

class IpqsValidationProvider implements ValidationProvider
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config = [],
    ) {}

    public function validateEmail(?string $email, ?ValidationContext $context = null): ValidationResult
    {
        if (blank($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ValidationResult::fail('Invalid email format', ['provider' => 'ipqs', 'check' => 'syntax']);
        }

        if (! $this->apiKey()) {
            return ValidationResult::fail('Fraud detection API key not configured', ['provider' => 'ipqs']);
        }

        $response = $this->request('email', rawurlencode($email), [
            'timeout' => (string) max(1, min(60, (int) ($this->config['email_timeout'] ?? 7))),
            'fast' => $this->boolString('email_fast', false),
            'abuse_strictness' => (string) max(0, min(2, (int) ($this->config['email_abuse_strictness'] ?? 0))),
            'strictness' => (string) ($this->config['strictness'] ?? 1),
        ]);

        if ($response === null) {
            return ValidationResult::fail('Email fraud check unavailable', ['provider' => 'ipqs']);
        }

        $fraudScore = (int) ($response['fraud_score'] ?? 0);
        $valid = (bool) ($response['valid'] ?? false);
        $disposable = (bool) ($response['disposable'] ?? false);
        $honeypot = (bool) ($response['honeypot'] ?? false);
        $catchAll = (bool) ($response['catch_all'] ?? false);
        $recentAbuse = (bool) ($response['recent_abuse'] ?? false);
        $leaked = (bool) ($response['leaked'] ?? false);
        $spamTrap = (string) ($response['spam_trap_score'] ?? 'none');
        $threshold = (int) ($this->config['fraud_score_threshold'] ?? 85);

        $passed = $valid
            && $fraudScore < $threshold
            && (! $this->bool('block_disposable_email', true) || ! $disposable)
            && (! $this->bool('block_spam_trap_email', true) || (! $honeypot && $spamTrap === 'none'))
            && (! $this->bool('block_catch_all_email', false) || ! $catchAll)
            && (! $this->bool('block_recent_abuse_email', true) || ! $recentAbuse)
            && (! $this->bool('block_leaked_email', false) || ! $leaked);

        $meta = [
            'provider' => 'ipqs',
            'check' => 'email',
            'valid' => $valid,
            'fraud_score' => $fraudScore,
            'disposable' => $disposable,
            'honeypot' => $honeypot,
            'catch_all' => $catchAll,
            'recent_abuse' => $recentAbuse,
            'leaked' => $leaked,
            'spam_trap_score' => $spamTrap,
            'smtp_score' => $response['smtp_score'] ?? null,
            'overall_score' => $response['overall_score'] ?? null,
            'deliverability' => $response['deliverability'] ?? null,
            'status' => $passed ? 'deliverable' : 'high_risk',
        ];

        if ($passed) {
            return ValidationResult::pass($meta);
        }

        $reason = match (true) {
            ! $valid => 'Email address is invalid or undeliverable',
            $disposable && $this->bool('block_disposable_email', true) => 'Disposable or temporary email address',
            ($honeypot || $spamTrap !== 'none') && $this->bool('block_spam_trap_email', true) => 'Email flagged as spam trap or honeypot',
            $catchAll && $this->bool('block_catch_all_email', false) => 'Catch-all email domain',
            $recentAbuse && $this->bool('block_recent_abuse_email', true) => 'Email associated with recent abuse',
            $leaked && $this->bool('block_leaked_email', false) => 'Email found in data leaks',
            $fraudScore >= $threshold => "Email fraud score too high ({$fraudScore})",
            default => 'Email failed fraud risk checks',
        };

        return ValidationResult::fail($reason, $meta);
    }

    public function validateHlr(?string $phone, ?ValidationContext $context = null): ValidationResult
    {
        if (blank($phone)) {
            return ValidationResult::fail('Phone number missing', ['provider' => 'ipqs', 'check' => 'hlr']);
        }

        if (! $this->apiKey()) {
            return ValidationResult::fail('Fraud detection API key not configured', ['provider' => 'ipqs']);
        }

        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) < 10) {
            return ValidationResult::fail('Invalid phone number length', ['provider' => 'ipqs', 'check' => 'hlr']);
        }

        $query = [];
        $countries = $this->phoneCountries();
        if ($countries !== []) {
            $query['country'] = $countries;
        }

        $response = $this->request('phone', rawurlencode($phone), $query);

        if ($response === null) {
            return ValidationResult::fail('Phone fraud check unavailable', ['provider' => 'ipqs']);
        }

        $fraudScore = (int) ($response['fraud_score'] ?? 0);
        $valid = (bool) ($response['valid'] ?? false);
        $active = (bool) ($response['active'] ?? false);
        $voip = (bool) ($response['VOIP'] ?? $response['voip'] ?? false);
        $prepaid = (bool) ($response['prepaid'] ?? false);
        $risky = (bool) ($response['risky'] ?? false);
        $recentAbuse = (bool) ($response['recent_abuse'] ?? false);
        $spammer = (bool) ($response['spammer'] ?? false);
        $threshold = (int) ($this->config['fraud_score_threshold'] ?? 85);

        $passed = $valid
            && $active
            && $fraudScore < $threshold
            && (! $this->bool('block_voip', false) || ! $voip)
            && (! $this->bool('block_prepaid', false) || ! $prepaid)
            && (! $this->bool('block_risky_phone', true) || ! $risky)
            && (! $this->bool('block_recent_abuse_phone', true) || ! $recentAbuse)
            && (! $this->bool('block_spammer_phone', true) || ! $spammer);

        $meta = [
            'provider' => 'ipqs',
            'check' => 'hlr',
            'valid' => $valid,
            'active' => $active,
            'fraud_score' => $fraudScore,
            'line_type' => $response['line_type'] ?? null,
            'carrier' => $response['carrier'] ?? null,
            'voip' => $voip,
            'prepaid' => $prepaid,
            'risky' => $risky,
            'recent_abuse' => $recentAbuse,
            'spammer' => $spammer,
            'status' => $passed ? 'reachable' : 'high_risk',
        ];

        if ($passed) {
            return ValidationResult::pass($meta);
        }

        $reason = match (true) {
            ! $valid => 'Phone number is invalid',
            ! $active => 'Phone number is not active or reachable',
            $voip && $this->bool('block_voip', false) => 'VOIP phone number detected',
            $prepaid && $this->bool('block_prepaid', false) => 'Prepaid phone line detected',
            $risky && $this->bool('block_risky_phone', true) => 'Phone number flagged as risky',
            $recentAbuse && $this->bool('block_recent_abuse_phone', true) => 'Phone associated with recent abuse',
            $spammer && $this->bool('block_spammer_phone', true) => 'Phone flagged as spammer',
            $fraudScore >= $threshold => "Phone fraud score too high ({$fraudScore})",
            default => 'Phone failed fraud risk checks',
        };

        return ValidationResult::fail($reason, $meta);
    }

    public function validateIp(?string $ip, ?ValidationContext $context = null): ValidationResult
    {
        if (blank($ip) || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return ValidationResult::fail('IP address missing or invalid', ['provider' => 'ipqs', 'check' => 'ip']);
        }

        if (! $this->apiKey()) {
            return ValidationResult::fail('Fraud detection API key not configured', ['provider' => 'ipqs']);
        }

        $query = [
            'strictness' => (string) max(0, min(3, (int) ($this->config['strictness'] ?? 1))),
            'allow_public_access_points' => $this->boolString('allow_public_access_points', true),
            'lighter_penalties' => $this->boolString('lighter_penalties', false),
            'fast' => $this->boolString('ip_fast', false),
        ];

        if ($this->bool('pass_user_agent', true) && filled($context?->userAgent)) {
            $query['user_agent'] = $context->userAgent;
        }

        if (filled($context?->userLanguage)) {
            $query['user_language'] = $context->userLanguage;
        }

        $response = $this->request('ip', rawurlencode($ip), $query);

        if ($response === null) {
            return ValidationResult::fail('IP fraud check unavailable', ['provider' => 'ipqs']);
        }

        $fraudScore = (int) ($response['fraud_score'] ?? 0);
        $threshold = (int) ($this->config['fraud_score_threshold'] ?? 85);
        $proxy = (bool) ($response['proxy'] ?? false);
        $vpn = (bool) ($response['vpn'] ?? false);
        $tor = (bool) ($response['tor'] ?? false);
        $bot = (bool) ($response['bot_status'] ?? false);
        $crawler = (bool) ($response['is_crawler'] ?? false);
        $recentAbuse = (bool) ($response['recent_abuse'] ?? false);
        $mobile = (bool) ($response['mobile'] ?? false);

        if ($crawler && $this->bool('allow_crawlers', true)) {
            return ValidationResult::pass([
                'provider' => 'ipqs',
                'check' => 'ip',
                'fraud_score' => $fraudScore,
                'is_crawler' => true,
                'status' => 'crawler_allowed',
            ]);
        }

        if ($mobile && $this->bool('lower_penalty_for_mobiles', false)) {
            $passed = ($fraudScore < $threshold)
                && (! $vpn || ! $this->bool('block_vpn', true))
                && (! $tor || ! $this->bool('block_tor', true));
        } else {
            $passed = $fraudScore < $threshold
                && (! $this->bool('block_proxy', true) || ! $proxy)
                && (! $this->bool('block_vpn', true) || ! $vpn)
                && (! $this->bool('block_tor', true) || ! $tor)
                && (! $this->bool('block_bots', false) || ! $bot)
                && (! $this->bool('block_recent_abuse_ip', true) || ! $recentAbuse);
        }

        $meta = [
            'provider' => 'ipqs',
            'check' => 'ip',
            'fraud_score' => $fraudScore,
            'proxy' => $proxy,
            'vpn' => $vpn,
            'tor' => $tor,
            'bot_status' => $bot,
            'is_crawler' => $crawler,
            'recent_abuse' => $recentAbuse,
            'mobile' => $mobile,
            'country_code' => $response['country_code'] ?? null,
            'isp' => $response['ISP'] ?? $response['isp'] ?? null,
            'connection_type' => $response['connection_type'] ?? null,
            'status' => $passed ? 'clean' : 'high_risk',
        ];

        if ($passed) {
            return ValidationResult::pass($meta);
        }

        $reason = match (true) {
            $tor && $this->bool('block_tor', true) => 'Tor exit node detected',
            $vpn && $this->bool('block_vpn', true) => 'VPN connection detected',
            $proxy && $this->bool('block_proxy', true) => 'Proxy connection detected',
            $bot && $this->bool('block_bots', false) => 'Bot traffic detected',
            $recentAbuse && $this->bool('block_recent_abuse_ip', true) => 'IP associated with recent abuse',
            $fraudScore >= $threshold => "IP fraud score too high ({$fraudScore})",
            default => 'IP failed fraud risk checks',
        };

        return ValidationResult::fail($reason, $meta);
    }

    public function validateUrl(?string $url, ?ValidationContext $context = null): ValidationResult
    {
        if (blank($url)) {
            return ValidationResult::fail('URL missing', ['provider' => 'ipqs', 'check' => 'url']);
        }

        $url = trim($url);
        if (! str_contains($url, '://')) {
            $url = 'https://'.$url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return ValidationResult::fail('URL format invalid', ['provider' => 'ipqs', 'check' => 'url']);
        }

        if (! $this->apiKey()) {
            return ValidationResult::fail('Fraud detection API key not configured', ['provider' => 'ipqs']);
        }

        $response = $this->request('url', rawurlencode($url), [
            'strictness' => (string) max(0, min(2, (int) ($this->config['url_strictness'] ?? 0))),
        ]);

        if ($response === null) {
            return ValidationResult::fail('URL fraud check unavailable', ['provider' => 'ipqs']);
        }

        $riskScore = (int) ($response['risk_score'] ?? 0);
        $threshold = (int) ($this->config['url_risk_threshold'] ?? 85);
        $phishing = (bool) ($response['phishing'] ?? false);
        $malware = (bool) ($response['malware'] ?? false);
        $suspicious = (bool) ($response['suspicious'] ?? false);
        $parking = (bool) ($response['parking'] ?? false);
        $spamming = (bool) ($response['spamming'] ?? false);
        $unsafe = (bool) ($response['unsafe'] ?? false);

        $passed = $riskScore < $threshold
            && (! $this->bool('block_phishing_url', true) || ! $phishing)
            && (! $this->bool('block_malware_url', true) || ! $malware)
            && (! $this->bool('block_suspicious_url', false) || ! $suspicious)
            && (! $this->bool('block_parked_url', false) || ! $parking)
            && (! $this->bool('block_spam_url', true) || ! $spamming)
            && ! $unsafe;

        $meta = [
            'provider' => 'ipqs',
            'check' => 'url',
            'risk_score' => $riskScore,
            'domain' => $response['domain'] ?? null,
            'phishing' => $phishing,
            'malware' => $malware,
            'suspicious' => $suspicious,
            'parking' => $parking,
            'spamming' => $spamming,
            'unsafe' => $unsafe,
            'category' => $response['category'] ?? null,
            'status' => $passed ? 'clean' : 'high_risk',
        ];

        if ($passed) {
            return ValidationResult::pass($meta);
        }

        $reason = match (true) {
            $phishing && $this->bool('block_phishing_url', true) => 'Phishing URL detected',
            $malware && $this->bool('block_malware_url', true) => 'Malware URL detected',
            $spamming && $this->bool('block_spam_url', true) => 'Spam URL domain detected',
            $parking && $this->bool('block_parked_url', false) => 'Parked domain detected',
            $suspicious && $this->bool('block_suspicious_url', false) => 'Suspicious URL detected',
            $unsafe => 'URL marked unsafe',
            $riskScore >= $threshold => "URL risk score too high ({$riskScore})",
            default => 'URL failed fraud risk checks',
        };

        return ValidationResult::fail($reason, $meta);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    protected function request(string $type, string $value, array $query = []): ?array
    {
        $url = sprintf(
            'https://www.ipqualityscore.com/api/json/%s/%s/%s',
            $type,
            $this->apiKey(),
            $value,
        );

        $response = Http::timeout(15)
            ->acceptJson()
            ->get($url, $query);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data) || ! ($data['success'] ?? false)) {
            return null;
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    protected function phoneCountries(): array
    {
        $raw = (string) ($this->config['phone_countries'] ?? 'GB,US,IE');

        return array_values(array_filter(array_map(
            fn (string $c) => strtoupper(trim($c)),
            explode(',', $raw),
        )));
    }

    protected function apiKey(): ?string
    {
        $key = $this->config['api_key'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    protected function bool(string $key, bool $default): bool
    {
        return (bool) ($this->config[$key] ?? $default);
    }

    protected function boolString(string $key, bool $default): string
    {
        return $this->bool($key, $default) ? 'true' : 'false';
    }
}
