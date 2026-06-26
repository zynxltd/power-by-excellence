<?php

namespace App\Support;

use App\Models\Campaign;

class CampaignRegion
{
    /**
     * @return array<string, string>
     */
    public static function countryLabels(): array
    {
        return [
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'IE' => 'Ireland',
            'DE' => 'Germany',
            'FR' => 'France',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'PT' => 'Portugal',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'PL' => 'Poland',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'ZA' => 'South Africa',
            'IN' => 'India',
            'AE' => 'United Arab Emirates',
            'SG' => 'Singapore',
            'MX' => 'Mexico',
            'BR' => 'Brazil',
        ];
    }

    public static function flagEmoji(?string $countryCode): string
    {
        $code = strtoupper(trim((string) $countryCode));

        if ($code === '' || strlen($code) !== 2) {
            return '🌍';
        }

        $chars = str_split($code);

        return mb_chr(0x1F1E6 + ord($chars[0]) - 65)
            .mb_chr(0x1F1E6 + ord($chars[1]) - 65);
    }

    /**
     * @return array{type: string, code: ?string, emoji: string, label: string, is_multi: bool}
     */
    public static function forCampaign(Campaign $campaign): array
    {
        $geoCountries = collect($campaign->geo_countries ?? [])
            ->map(fn ($code) => strtoupper((string) $code))
            ->filter(fn ($code) => strlen($code) === 2)
            ->unique()
            ->values();

        $isMulti = (bool) $campaign->multi_geo || $geoCountries->count() > 1;

        if ($isMulti) {
            $label = $geoCountries->isNotEmpty()
                ? 'Multi-geo: '.$geoCountries->join(', ')
                : 'Multi-geo / worldwide';

            return [
                'type' => 'world',
                'code' => null,
                'emoji' => '🌍',
                'label' => $label,
                'is_multi' => true,
            ];
        }

        $code = $geoCountries->first() ?: strtoupper((string) $campaign->country);
        $labels = self::countryLabels();

        return [
            'type' => 'country',
            'code' => $code,
            'emoji' => self::flagEmoji($code),
            'label' => $labels[$code] ?? $code,
            'is_multi' => false,
        ];
    }
}
