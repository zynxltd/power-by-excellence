<?php

namespace App\Support\Products;

use App\Models\Account;

class CallLogicProduct
{
    public const PRODUCT_KEY = 'call_logic';

    public static function isEnabled(?Account $account): bool
    {
        if (! $account) {
            return false;
        }

        $products = $account->settings['products'] ?? ['lms_sync'];

        return in_array(self::PRODUCT_KEY, $products, true);
    }

    public static function settings(?Account $account): array
    {
        $defaults = [
            'max_tracking_numbers' => 50,
            'recording_enabled' => config('telephony.recording_enabled', false),
            'concurrent_call_cap' => 100,
        ];

        return array_merge($defaults, $account?->settings['call_logic'] ?? []);
    }

    public static function enable(Account $account): void
    {
        $settings = $account->settings ?? [];
        $products = $settings['products'] ?? ['lms_sync'];

        if (! in_array(self::PRODUCT_KEY, $products, true)) {
            $products[] = self::PRODUCT_KEY;
        }

        $settings['products'] = array_values(array_unique($products));
        $account->update(['settings' => $settings]);
    }

    public static function disable(Account $account): void
    {
        $settings = $account->settings ?? [];
        $products = array_values(array_filter(
            $settings['products'] ?? ['lms_sync'],
            fn (string $p) => $p !== self::PRODUCT_KEY,
        ));

        $settings['products'] = $products ?: ['lms_sync'];
        $account->update(['settings' => $settings]);
    }
}
