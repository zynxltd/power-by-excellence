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

    public static function verifiedCustomHost(Account $account): ?string
    {
        if (! self::isVerified($account)) {
            return null;
        }

        return self::customHost($account);
    }

    public static function isVerified(Account $account): bool
    {
        $customHost = self::customHost($account);

        if ($customHost === null) {
            return false;
        }

        return filled($account->settings['custom_portal_domain_verified_at'] ?? null);
    }

    public static function verifiedAt(Account $account): ?string
    {
        if (! self::isVerified($account)) {
            return null;
        }

        return (string) $account->settings['custom_portal_domain_verified_at'];
    }

    public static function legacyHost(Account $account): ?string
    {
        return self::normalize($account->domain);
    }

    public static function portalHost(Account $account): string
    {
        return self::verifiedCustomHost($account)
            ?? self::legacyHost($account)
            ?? ($account->slug.'.'.\App\Support\Tenancy\TenantResolver::baseDomain());
    }

    /**
     * @return list<string>
     */
    public static function hostsForAccount(Account $account): array
    {
        $hosts = array_filter([
            self::verifiedCustomHost($account),
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

    public static function resolveAccount(string $host): ?Account
    {
        $host = self::normalize($host);

        if ($host === null) {
            return null;
        }

        return Account::query()
            ->where('is_active', true)
            ->where('settings->custom_portal_domain', $host)
            ->whereNotNull('settings->custom_portal_domain_verified_at')
            ->first();
    }
}
