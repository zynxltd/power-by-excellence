<?php

namespace App\Enums;

enum LeadStatus: string
{
    case Pending = 'pending';
    case Validating = 'validating';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Distributing = 'distributing';
    case Sold = 'sold';
    case Unsold = 'unsold';
    case Quarantined = 'quarantined';
    case Returned = 'returned';
    case Duplicate = 'duplicate';

    /**
     * Statuses shown in the live queue while a lead is still moving through the pipeline.
     *
     * @return list<string>
     */
    public static function inFlightValues(): array
    {
        return [
            self::Pending->value,
            self::Validating->value,
            self::Accepted->value,
            self::Distributing->value,
        ];
    }

    /**
     * Statuses actively being worked on (validation or distribution).
     *
     * @return list<string>
     */
    public static function processingValues(): array
    {
        return [
            self::Validating->value,
            self::Distributing->value,
        ];
    }
}
