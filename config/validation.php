<?php

return [
    'driver' => env('VALIDATION_DRIVER', 'demo'),

    'demo' => [
        'reject_domains' => ['invalid.demo', 'bounce.demo', 'trap.demo'],
        'hlr_unreachable_prefixes' => ['07000', '08000'],
        'high_risk_ip_prefixes' => ['10.66.', '198.51.100.'],
        'malicious_url_hosts' => ['malware.demo', 'phish.demo'],
    ],

    'ipqs' => [
        'api_key' => env('IPQS_API_KEY'),
        'fraud_score_threshold' => (int) env('IPQS_FRAUD_SCORE_THRESHOLD', 85),
        'url_risk_threshold' => (int) env('IPQS_URL_RISK_THRESHOLD', 85),

        // Email verification API
        'email_timeout' => (int) env('IPQS_EMAIL_TIMEOUT', 7),
        'email_fast' => env('IPQS_EMAIL_FAST', false),
        'email_abuse_strictness' => (int) env('IPQS_EMAIL_ABUSE_STRICTNESS', 0),
        'block_disposable_email' => env('IPQS_BLOCK_DISPOSABLE_EMAIL', true),
        'block_spam_trap_email' => env('IPQS_BLOCK_SPAM_TRAP_EMAIL', true),
        'block_catch_all_email' => env('IPQS_BLOCK_CATCH_ALL_EMAIL', false),
        'block_recent_abuse_email' => env('IPQS_BLOCK_RECENT_ABUSE_EMAIL', true),
        'block_leaked_email' => env('IPQS_BLOCK_LEAKED_EMAIL', false),

        // Phone validation API
        'phone_countries' => env('IPQS_PHONE_COUNTRIES', 'GB,US,IE'),
        'block_voip' => env('IPQS_BLOCK_VOIP', false),
        'block_prepaid' => env('IPQS_BLOCK_PREPAID', false),
        'block_risky_phone' => env('IPQS_BLOCK_RISKY_PHONE', true),
        'block_recent_abuse_phone' => env('IPQS_BLOCK_RECENT_ABUSE_PHONE', true),
        'block_spammer_phone' => env('IPQS_BLOCK_SPAMMER_PHONE', true),

        // Proxy / VPN detection API
        'strictness' => (int) env('IPQS_STRICTNESS', 1),
        'allow_public_access_points' => env('IPQS_ALLOW_PUBLIC_ACCESS_POINTS', true),
        'lighter_penalties' => env('IPQS_LIGHTER_PENALTIES', false),
        'ip_fast' => env('IPQS_IP_FAST', false),
        'block_vpn' => env('IPQS_BLOCK_VPN', true),
        'block_proxy' => env('IPQS_BLOCK_PROXY', true),
        'block_tor' => env('IPQS_BLOCK_TOR', true),
        'block_bots' => env('IPQS_BLOCK_BOTS', false),
        'block_recent_abuse_ip' => env('IPQS_BLOCK_RECENT_ABUSE_IP', true),
        'allow_crawlers' => env('IPQS_ALLOW_CRAWLERS', true),
        'lower_penalty_for_mobiles' => env('IPQS_LOWER_PENALTY_FOR_MOBILES', false),
        'pass_user_agent' => env('IPQS_PASS_USER_AGENT', true),
        'ip_whitelist' => env('IPQS_IP_WHITELIST', ''),

        // Malicious URL scanner API
        'url_strictness' => (int) env('IPQS_URL_STRICTNESS', 0),
        'block_phishing_url' => env('IPQS_BLOCK_PHISHING_URL', true),
        'block_malware_url' => env('IPQS_BLOCK_MALWARE_URL', true),
        'block_suspicious_url' => env('IPQS_BLOCK_SUSPICIOUS_URL', false),
        'block_parked_url' => env('IPQS_BLOCK_PARKED_URL', false),
        'block_spam_url' => env('IPQS_BLOCK_SPAM_URL', true),
    ],

    'quarantine_on_email_fail' => true,
    'quarantine_on_hlr_fail' => true,
    'quarantine_hours' => 48,
    'quarantine_expire_action' => env('QUARANTINE_EXPIRE_ACTION', 'release'),
];
