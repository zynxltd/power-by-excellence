<?php

namespace App\Support\BuyerPortal;

use App\Models\Account;
use App\Models\Buyer;

class BuyerPortalLocale
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return config('buyer_portal_languages.options', ['en' => 'English']);
    }

    public static function default(): string
    {
        return config('buyer_portal_languages.default', 'en');
    }

    public static function isValid(?string $locale): bool
    {
        return filled($locale) && array_key_exists($locale, static::options());
    }

    public static function resolve(?Account $account, ?Buyer $buyer): string
    {
        $override = $buyer?->settings['portal_locale'] ?? null;
        if (static::isValid($override)) {
            return $override;
        }

        $tenantDefault = $account?->settings['buyer_portal_locale'] ?? null;
        if (static::isValid($tenantDefault)) {
            return $tenantDefault;
        }

        return static::default();
    }

    /**
     * @return array<string, mixed>
     */
    public static function translations(string $locale): array
    {
        $locale = static::isValid($locale) ? $locale : static::default();

        return trans('buyer_portal', [], $locale);
    }

    /**
     * @return array{locale: string, strings: array<string, mixed>, languages: array<string, string>}|null
     */
    public static function inertiaPayload(?Account $account, ?Buyer $buyer): ?array
    {
        if (! $account && ! $buyer) {
            return null;
        }

        $locale = static::resolve($account, $buyer);

        return [
            'locale' => $locale,
            'strings' => static::translations($locale),
            'languages' => static::options(),
            'branding' => $buyer?->portalBranding() ?? [
                'logo_url' => null,
                'primary_color' => null,
                'welcome_text' => null,
            ],
        ];
    }
}
