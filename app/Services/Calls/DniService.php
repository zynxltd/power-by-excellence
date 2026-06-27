<?php

namespace App\Services\Calls;

use App\Models\TrackingNumber;

class DniService
{
    /**
     * Resolve tracking number for DNI pool based on attribution params.
     */
    public function resolve(int $accountId, ?int $campaignId, array $params = []): ?TrackingNumber
    {
        $query = TrackingNumber::where('account_id', $accountId)
            ->where('status', 'active');

        if ($campaignId) {
            $query->where(function ($q) use ($campaignId) {
                $q->where('campaign_id', $campaignId)->orWhereNull('campaign_id');
            });
        }

        $pool = $params['pool'] ?? $params['dni_pool'] ?? null;

        if ($pool) {
            $query->where('dni_pool', $pool);
        }

        $numbers = $query->get();

        if ($numbers->isEmpty()) {
            return null;
        }

        if ($sid = $params['sid'] ?? null) {
            $matched = $numbers->first(fn (TrackingNumber $n) => ($n->dni_rules['sid'] ?? null) === $sid);
            if ($matched) {
                return $matched;
            }
        }

        return $numbers->random();
    }
}
