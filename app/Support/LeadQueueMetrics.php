<?php

namespace App\Support;

use App\Enums\LeadStatus;

class LeadQueueMetrics
{
    /**
     * @param  array<string, int|string>  $counts
     * @return array{pending: int, processing: int, accepted: int, quarantined: int}
     */
    public static function queueBreakdown(array $counts): array
    {
        return [
            'pending' => (int) ($counts[LeadStatus::Pending->value] ?? 0),
            'processing' => (int) (($counts[LeadStatus::Validating->value] ?? 0)
                + ($counts[LeadStatus::Distributing->value] ?? 0)),
            'accepted' => (int) ($counts[LeadStatus::Accepted->value] ?? 0),
            'quarantined' => (int) ($counts[LeadStatus::Quarantined->value] ?? 0),
        ];
    }

    /**
     * @param  array<string, int|string>  $counts
     * @return array<string, int>
     */
    public static function pipelineSummary(array $counts, int $total): array
    {
        return [
            'total' => $total,
            'pending' => (int) ($counts[LeadStatus::Pending->value] ?? 0),
            'processing' => self::queueBreakdown($counts)['processing'],
            'sold' => (int) ($counts[LeadStatus::Sold->value] ?? 0),
            'unsold' => (int) ($counts[LeadStatus::Unsold->value] ?? 0),
            'rejected' => (int) ($counts[LeadStatus::Rejected->value] ?? 0),
            'quarantined' => (int) ($counts[LeadStatus::Quarantined->value] ?? 0),
            'duplicate' => (int) ($counts[LeadStatus::Duplicate->value] ?? 0),
        ];
    }
}
