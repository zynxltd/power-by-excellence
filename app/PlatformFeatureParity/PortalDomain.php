<?php

namespace App\PlatformFeatureParity;

use App\Models\Account;

/**
 * Resolves branded portal hostnames from account settings and legacy columns.
 */
final class PortalDomain
{
    public static function normalize(?string $host): ?string
    {
        if ($host === null || trim($host) === '') {
            return null;
        }

        $host = strtolower(trim($host));
        $host = preg_replace('#^https?://#', '', $host);
        $host = rtrim($host, '/');

        return $host !== '' ? $host : null;
    }

    public static function customHost(Account $account): ?string
    {
        return self::normalize($account->settings['custom_portal_domain'] ?? null);
    }

    public static function legacyHost(Account $account): ?string
    {
        return self::normalize($account->domain);
    }

    public static function portalHost(Account $account): string
    {
        return self::customHost($account)
            ?? self::legacyHost($account)
            ?? ($account->slug.'.'.\App\Support\Tenancy\TenantResolver::baseDomain());
    }

    public static function hostsForAccount(Account $account): array
    {
        $hosts = array_filter([
            self::customHost($account),
            self::legacyHost($account),
            self::normalize($account->slug.'.'.\App\Support\Tenancy\TenantResolver::baseDomain()),
        ]);

        return array_values(array_unique($hosts));
    }

    public static function matches(Account $account, string $host): bool
    {
        $host = self::normalize($host);

        if ($host === null) {
            return false;
        }

        return in_array($host, self::hostsForAccount($account), true);
    }
}
