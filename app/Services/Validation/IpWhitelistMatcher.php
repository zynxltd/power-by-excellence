<?php

namespace App\Services\Validation;

class IpWhitelistMatcher
{
    public static function isWhitelisted(string $ip, ?string $rules): bool
    {
        if (blank($rules)) {
            return false;
        }

        foreach (preg_split('/[\s,]+/', trim($rules), -1, PREG_SPLIT_NO_EMPTY) as $rule) {
            if ($rule === $ip) {
                return true;
            }

            if (str_contains($rule, '/')) {
                if (self::ipInCidr($ip, $rule)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function whitelistFromContext(?ValidationContext $context, array $config = []): ?string
    {
        $rules = $context?->ipWhitelist;

        if (blank($rules)) {
            $rules = $config['ip_whitelist'] ?? null;
        }

        return filled($rules) ? (string) $rules : null;
    }

    protected static function ipInCidr(string $ip, string $cidr): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $parts = explode('/', $cidr, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$subnet, $maskBits] = $parts;
        if (! filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $maskBits = (int) $maskBits;
        if ($maskBits < 0 || $maskBits > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = $maskBits === 0 ? 0 : (-1 << (32 - $maskBits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
