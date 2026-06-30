<?php

namespace App\Services\Security;

use App\Models\Account;
use App\Services\Validation\IpWhitelistMatcher;

class AdminIpAllowlistService
{
    /**
     * @param  list<string>  $allowlist
     */
    public function allows(?string $ip, array $allowlist): bool
    {
        if (blank($ip) || $allowlist === []) {
            return false;
        }

        return IpWhitelistMatcher::isWhitelisted($ip, implode("\n", $allowlist));
    }

    public function isEnforcedForAccount(?Account $account): bool
    {
        if (! $account || $this->isBypassed()) {
            return false;
        }

        $policy = AdminIpAllowlistPolicy::forAccount($account);

        return $policy['admin_ip_allowlist_enabled'] && $policy['admin_ip_allowlist'] !== [];
    }

    public function isBypassed(): bool
    {
        return (bool) config('platform.security.admin_ip_allowlist_bypass', false);
    }
}
