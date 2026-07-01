<?php

namespace App\Enums;

enum LawfulBasis: string
{
    case Consent = 'consent';
    case LegitimateInterest = 'legitimate_interest';
    case Contract = 'contract';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
