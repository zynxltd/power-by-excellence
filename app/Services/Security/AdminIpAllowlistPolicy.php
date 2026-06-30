<?php

namespace App\Services\Security;

use App\Models\Account;

class AdminIpAllowlistPolicy
{
    public const SETTINGS_KEY = 'security';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'admin_ip_allowlist_enabled' => false,
            'admin_ip_allowlist' => [],
            'admin_geo_block_enabled' => false,
            'blocked_country_codes' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forAccount(Account $account): array
    {
        $stored = $account->settings[self::SETTINGS_KEY] ?? [];

        return [
            'admin_ip_allowlist_enabled' => (bool) ($stored['admin_ip_allowlist_enabled'] ?? false),
            'admin_ip_allowlist' => self::normalizeAllowlist($stored['admin_ip_allowlist'] ?? []),
            'admin_geo_block_enabled' => (bool) ($stored['admin_geo_block_enabled'] ?? false),
            'blocked_country_codes' => self::normalizeCountryCodes($stored['blocked_country_codes'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forInertia(Account $account): array
    {
        $policy = self::forAccount($account);

        return [
            ...$policy,
            'admin_ip_allowlist_text' => self::allowlistToText($policy['admin_ip_allowlist']),
            'blocked_country_codes_text' => implode(', ', $policy['blocked_country_codes']),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeInput(array $input): array
    {
        $allowlist = array_key_exists('admin_ip_allowlist', $input)
            ? self::normalizeAllowlist($input['admin_ip_allowlist'])
            : self::parseAllowlistText((string) ($input['admin_ip_allowlist_text'] ?? ''));

        return [
            'admin_ip_allowlist_enabled' => (bool) ($input['admin_ip_allowlist_enabled'] ?? false),
            'admin_ip_allowlist' => $allowlist,
            'admin_geo_block_enabled' => (bool) ($input['admin_geo_block_enabled'] ?? false),
            'blocked_country_codes' => self::normalizeCountryCodes(
                $input['blocked_country_codes'] ?? self::parseCountryCodesText((string) ($input['blocked_country_codes_text'] ?? ''))
            ),
        ];
    }

    /**
     * @param  array<int, string>|string|null  $allowlist
     * @return list<string>
     */
    public static function normalizeAllowlist(array|string|null $allowlist): array
    {
        if (is_string($allowlist)) {
            return self::parseAllowlistText($allowlist);
        }

        if (! is_array($allowlist)) {
            return [];
        }

        return collect($allowlist)
            ->map(fn ($entry) => trim((string) $entry))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function parseAllowlistText(string $text): array
    {
        if (blank($text)) {
            return [];
        }

        return collect(preg_split('/[\s,]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($entry) => trim((string) $entry))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $allowlist
     */
    public static function allowlistToText(array $allowlist): string
    {
        return implode("\n", $allowlist);
    }

    /**
     * @param  array<int, string>|string|null  $codes
     * @return list<string>
     */
    public static function normalizeCountryCodes(array|string|null $codes): array
    {
        if (is_string($codes)) {
            return self::parseCountryCodesText($codes);
        }

        if (! is_array($codes)) {
            return [];
        }

        return collect($codes)
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter(fn ($code) => strlen($code) === 2)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function parseCountryCodesText(string $text): array
    {
        if (blank($text)) {
            return [];
        }

        return self::normalizeCountryCodes(preg_split('/[\s,]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
