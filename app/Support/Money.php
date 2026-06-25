<?php

namespace App\Support;

use Illuminate\Support\Number;

class Money
{
    public const LOCALE_BY_CURRENCY = [
        'GBP' => 'en-GB',
        'USD' => 'en-US',
        'CAD' => 'en-CA',
        'AUD' => 'en-AU',
        'NZD' => 'en-NZ',
        'EUR' => 'de-DE',
        'ZAR' => 'en-ZA',
        'INR' => 'en-IN',
        'AED' => 'ar-AE',
    ];

    public static function localeFor(string $currency): string
    {
        return self::LOCALE_BY_CURRENCY[strtoupper($currency)] ?? 'en-GB';
    }

    public static function format(float|int|string|null $amount, string $currency = 'GBP', int $decimals = 2): string
    {
        return Number::currency((float) ($amount ?? 0), strtoupper($currency), self::localeFor($currency));
    }
}
