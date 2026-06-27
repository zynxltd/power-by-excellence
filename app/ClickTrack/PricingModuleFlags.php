<?php

namespace App\ClickTrack;

/**
 * Pricing page module flags for Integration Lead (Marketing/Pricing.vue).
 * Wire: import plans via Inertia from a controller using PricingModuleFlags::forPage().
 */
final class PricingModuleFlags
{
    /**
     * @return array<string, mixed>
     */
    public static function forPage(): array
    {
        $pricing = config('click_track.pricing', []);
        $plans = config('click_track.plans', []);

        $tiers = [];
        foreach ($plans as $key => $plan) {
            $tier = $pricing['plans'][$key] ?? [];
            $tiers[$key] = [
                'plan_key' => $key,
                'label' => $plan['label'] ?? ucfirst($key),
                'included' => (bool) ($plan['included'] ?? false),
                'addon_available' => (bool) ($plan['addon_available'] ?? false),
                'clicks_cap' => $plan['clicks_cap'],
                'conversions_cap' => $plan['conversions_cap'],
                'clicks_display' => $tier['clicks_display'] ?? self::formatCap($plan['clicks_cap'] ?? null),
                'conversions_display' => $tier['conversions_display'] ?? self::formatCap($plan['conversions_cap'] ?? null),
                'overage_click' => $plan['overage_click'],
                'overage_conversion' => $plan['overage_conversion'],
                'feature_bullet' => $tier['feature_bullet'] ?? null,
                'marketing_label' => $tier['marketing_label'] ?? null,
                'show_on_pricing' => (bool) ($tier['show_on_pricing'] ?? true),
            ];
        }

        return [
            'product_key' => $pricing['product_key'] ?? 'click_track',
            'display_name' => $pricing['display_name'] ?? 'Click Track',
            'tagline' => $pricing['tagline'] ?? 'Affiliate link tracking, clicks, and conversions',
            'tiers' => $tiers,
            'usage_row_labels' => [
                'clicks' => 'Tracked clicks / month (Click Track)',
                'conversions' => 'Conversions tracked / month',
            ],
        ];
    }

    /**
     * Growth tier feature bullet for pricing cards (Integration Lead copy-paste fallback).
     */
    public static function growthFeatureBullet(): ?string
    {
        return config('click_track.pricing.plans.growth.feature_bullet');
    }

    protected static function formatCap(?int $cap): string
    {
        if ($cap === null) {
            return 'Unlimited';
        }

        return number_format($cap);
    }
}
