<?php

namespace App\PlatformFeatureParity;

use App\Models\Account;
use Illuminate\Support\Str;

final class PortalDomainVerification
{
    public const TXT_HOST_PREFIX = '_portal-verify';

    /**
     * @param  callable(string, int): array<int, array<string, mixed>>|null  $dnsResolver
     */
    public function __construct(
        protected $dnsResolver = null,
    ) {}

    public function cnameTarget(Account $account): string
    {
        $configured = PortalDomain::normalize((string) config('tenancy.portal_cname_target', ''));

        if ($configured !== null) {
            return $configured;
        }

        return $account->slug.'.'.\App\Support\Tenancy\TenantResolver::baseDomain();
    }

    public function txtHost(string $customHost): string
    {
        return self::TXT_HOST_PREFIX.'.'.PortalDomain::normalize($customHost);
    }

    public function ensureToken(Account $account): string
    {
        $settings = $account->settings ?? [];
        $token = $settings['custom_portal_domain_verification_token'] ?? null;

        if (filled($token)) {
            return (string) $token;
        }

        $token = Str::lower(Str::random(32));
        $settings['custom_portal_domain_verification_token'] = $token;
        $account->update(['settings' => $settings]);
        $account->refresh();

        return $token;
    }

    public function clearVerification(Account $account): void
    {
        $settings = $account->settings ?? [];
        unset(
            $settings['custom_portal_domain_verified_at'],
            $settings['custom_portal_domain_verification_token'],
        );
        $account->update(['settings' => $settings]);
    }

    public function markVerified(Account $account): void
    {
        $settings = $account->settings ?? [];
        $settings['custom_portal_domain_verified_at'] = now()->toIso8601String();
        $account->update(['settings' => $settings]);
        $account->refresh();
    }

    /**
     * @return array{verified: bool, method: ?string, message: string}
     */
    public function verify(Account $account): array
    {
        $customHost = PortalDomain::customHost($account);

        if ($customHost === null) {
            return [
                'verified' => false,
                'method' => null,
                'message' => 'Enter a custom portal domain before verifying DNS.',
            ];
        }

        $target = $this->cnameTarget($account);

        if ($this->cnamePointsToTarget($customHost, $target)) {
            $this->markVerified($account);

            return [
                'verified' => true,
                'method' => 'cname',
                'message' => 'CNAME record verified. Custom portal domain is active.',
            ];
        }

        $token = $this->ensureToken($account);

        if ($this->txtContainsToken($this->txtHost($customHost), $token)) {
            $this->markVerified($account);

            return [
                'verified' => true,
                'method' => 'txt',
                'message' => 'TXT verification record confirmed. Custom portal domain is active.',
            ];
        }

        return [
            'verified' => false,
            'method' => null,
            'message' => "DNS not verified yet. Point a CNAME for {$customHost} to {$target}, or add a TXT record at {$this->txtHost($customHost)} with value {$token}.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function statusForAccount(Account $account): array
    {
        $customHost = PortalDomain::customHost($account);
        $token = filled($customHost) ? ($account->settings['custom_portal_domain_verification_token'] ?? null) : null;

        if ($customHost !== null && blank($token) && ! PortalDomain::isVerified($account)) {
            $token = $this->ensureToken($account);
        }

        return [
            'custom_host' => $customHost,
            'verified' => PortalDomain::isVerified($account),
            'verified_at' => PortalDomain::verifiedAt($account),
            'cname_target' => $customHost !== null ? $this->cnameTarget($account) : null,
            'txt_host' => $customHost !== null ? $this->txtHost($customHost) : null,
            'txt_value' => $token,
        ];
    }

    protected function cnamePointsToTarget(string $customHost, string $target): bool
    {
        $target = PortalDomain::normalize($target);

        if ($target === null) {
            return false;
        }

        foreach ($this->dnsRecords($customHost, DNS_CNAME) as $record) {
            $targetHost = PortalDomain::normalize($record['target'] ?? null);

            if ($targetHost !== null && $this->hostMatchesTarget($targetHost, $target)) {
                return true;
            }
        }

        return false;
    }

    protected function txtContainsToken(string $txtHost, string $token): bool
    {
        foreach ($this->dnsRecords($txtHost, DNS_TXT) as $record) {
            $txt = (string) ($record['txt'] ?? '');

            if ($txt === $token || str_contains($txt, $token)) {
                return true;
            }
        }

        return false;
    }

    protected function hostMatchesTarget(string $resolved, string $expected): bool
    {
        if ($resolved === $expected) {
            return true;
        }

        return str_ends_with($resolved, '.'.$expected);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function dnsRecords(string $host, int $type): array
    {
        $resolver = $this->dnsResolver ?? fn (string $lookupHost, int $lookupType) => dns_get_record($lookupHost, $lookupType);
        $records = $resolver($host, $type);

        return is_array($records) ? $records : [];
    }
}
