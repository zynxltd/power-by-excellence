<?php

namespace App\Support\Tenancy;

use App\Models\Account;
use Illuminate\Http\Request;

class TenantResolver
{
    public static function baseDomain(): string
    {
        return (string) config('tenancy.base_domain', 'powerbyexcellence.test');
    }

    /**
     * @return list<string>
     */
    public static function centralHosts(): array
    {
        return config('tenancy.central_hosts', ['powerbyexcellence.test', 'localhost', '127.0.0.1']);
    }

    public static function host(?Request $request = null): string
    {
        return strtolower($request?->getHost() ?? request()->getHost());
    }

    public static function isCentralHost(?string $host = null): bool
    {
        $host = strtolower($host ?? self::host());

        foreach (self::centralHosts() as $central) {
            if ($host === strtolower($central)) {
                return true;
            }
        }

        return false;
    }

    public static function resolveFromHost(?string $host = null): ?Account
    {
        $host = strtolower($host ?? self::host());

        if (self::isCentralHost($host)) {
            return null;
        }

        $account = Account::query()->where('domain', $host)->where('is_active', true)->first();
        if ($account) {
            return $account;
        }

        $base = self::baseDomain();
        $suffix = '.'.$base;

        if (str_ends_with($host, $suffix)) {
            $subdomain = substr($host, 0, -strlen($suffix));

            if ($subdomain !== '') {
                return Account::query()
                    ->where('is_active', true)
                    ->where(function ($q) use ($subdomain, $host) {
                        $q->where('slug', $subdomain)->orWhere('domain', $host);
                    })
                    ->first();
            }
        }

        return null;
    }

    public static function portalHost(Account $account): string
    {
        if ($account->domain) {
            return $account->domain;
        }

        return $account->slug.'.'.self::baseDomain();
    }

    public static function portalUrl(Account $account, string $path = '/'): string
    {
        $scheme = request()->getScheme() ?: 'https';
        $path = '/'.ltrim($path, '/');

        return $scheme.'://'.self::portalHost($account).$path;
    }

    public static function centralUrl(string $path = '/'): string
    {
        $scheme = request()->getScheme() ?: 'https';
        $host = self::centralHosts()[0] ?? self::baseDomain();
        $path = '/'.ltrim($path, '/');

        return $scheme.'://'.$host.$path;
    }

    public static function apiBaseUrl(?Account $account = null): string
    {
        if ($account) {
            return rtrim(self::portalUrl($account, ''), '/').'/api/v1';
        }

        $scheme = request()->getScheme() ?: 'https';

        return rtrim($scheme.'://'.self::host(), '/').'/api/v1';
    }

    public static function forceRootUrl(?Account $account): void
    {
        if ($account) {
            \Illuminate\Support\Facades\URL::forceRootUrl(self::portalUrl($account, ''));
        } elseif (self::isCentralHost()) {
            $scheme = request()->getScheme() ?: 'https';
            \Illuminate\Support\Facades\URL::forceRootUrl($scheme.'://'.self::host());
        }
    }
}
