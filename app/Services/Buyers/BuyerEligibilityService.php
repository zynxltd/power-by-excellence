<?php

namespace App\Services\Buyers;

use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Lead;
use App\Services\Billing\BuyerBillingService;
use App\Services\Billing\RevenueCalculator;
use App\Services\Caps\CapService;
use App\Services\Scheduling\ScheduleService;

class BuyerEligibilityService
{
    public function __construct(
        protected CapService $capService,
        protected ScheduleService $scheduleService,
        protected BuyerBillingService $billingService,
        protected RevenueCalculator $revenueCalculator,
    ) {}

    public function canDeliver(Lead $lead, Delivery $delivery): bool
    {
        if (! $this->capService->hasCapacity('delivery', $delivery->id, $delivery->caps)) {
            return false;
        }

        if (! $this->capService->hasCapacity('campaign', $lead->campaign_id, $lead->campaign->caps)) {
            return false;
        }

        if ($delivery->buyer_id && $delivery->buyer) {
            if (! $this->buyerCanReceive($lead, $delivery->buyer, $delivery)) {
                return false;
            }
        }

        if (! $this->scheduleService->isWithinSchedule($delivery->schedule)) {
            return false;
        }

        $estimatedRevenue = $this->revenueCalculator->calculate(
            $delivery,
            $lead->allFields(),
        );

        if (! $this->capService->hasSpendCapacity('delivery', $delivery->id, $delivery->caps, $estimatedRevenue)) {
            return false;
        }

        if (! $this->capService->hasSpendCapacity('campaign', $lead->campaign_id, $lead->campaign->caps, $estimatedRevenue)) {
            return false;
        }

        return true;
    }

    public function buyerCanReceive(Lead $lead, Buyer $buyer, Delivery $delivery): bool
    {
        if (($buyer->status ?? 'active') !== 'active') {
            return false;
        }

        if (! $this->scheduleService->isWithinSchedule($buyer->schedule)) {
            return false;
        }

        if (! $this->capService->hasCapacity('buyer', $buyer->id, $buyer->caps)) {
            return false;
        }

        $estimatedRevenue = $this->revenueCalculator->calculate(
            $delivery,
            $lead->allFields(),
        );

        if (! $this->billingService->hasCredit($buyer, $estimatedRevenue)) {
            return false;
        }

        if (! $this->capService->hasSpendCapacity('buyer', $buyer->id, $buyer->caps, $estimatedRevenue)) {
            return false;
        }

        $settings = $buyer->settings ?? [];

        if (! empty($settings['exclusive_only'])) {
            $campaign = $lead->campaign;
            if ($campaign && $campaign->sell_mode !== 'exclusive') {
                return false;
            }
        }

        $minScore = isset($settings['min_quality_score']) ? (int) $settings['min_quality_score'] : null;
        if ($minScore !== null && $minScore > 0) {
            $score = (int) ($lead->metadata['quality_score'] ?? 100);
            if ($score < $minScore) {
                return false;
            }
        }

        $geo = $settings['geo_countries'] ?? [];
        if (! empty($geo)) {
            $country = strtoupper((string) ($lead->field_data['country'] ?? $lead->campaign?->country ?? ''));
            if ($country && ! in_array($country, array_map('strtoupper', $geo), true)) {
                return false;
            }
        }

        return true;
    }

    public function recordSuccessfulDelivery(Lead $lead, Delivery $delivery, float $revenue): void
    {
        $this->capService->increment('delivery', $delivery->id, $delivery->caps);
        $this->capService->incrementSpend('delivery', $delivery->id, $delivery->caps, $revenue);

        if ($delivery->buyer_id) {
            $this->capService->increment('buyer', $delivery->buyer_id, $delivery->buyer?->caps);
            $this->capService->incrementSpend('buyer', $delivery->buyer_id, $delivery->buyer?->caps, $revenue);
        }

        $this->capService->increment('campaign', $lead->campaign_id, $lead->campaign->caps);
        $this->capService->incrementSpend('campaign', $lead->campaign_id, $lead->campaign->caps, $revenue);

        if ($lead->source_id) {
            $source = $lead->source ?? $lead->source()->first();
            if ($source) {
                $this->capService->increment('source', $source->id, $source->caps);
            }
        }
    }

    public static function computeQualityScore(Lead $lead): int
    {
        return \App\Services\Leads\LeadQualityService::computeScore($lead);
    }
}
