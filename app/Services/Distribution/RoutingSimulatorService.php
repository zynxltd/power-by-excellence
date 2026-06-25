<?php

namespace App\Services\Distribution;

use App\Enums\RoutingMode;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Lead;
use App\Services\Billing\BuyerBillingService;
use App\Services\Caps\CapService;

class RoutingSimulatorService
{
    public function __construct(
        protected CapService $capService,
    ) {}

    public function simulate(Campaign $campaign, array $fieldData, ?Lead $lead = null): array
    {
        $lead = $lead ?? $this->syntheticLead($campaign, $fieldData);
        $steps = [];
        $config = $campaign->distributionConfigs()->where('is_active', true)->first();

        if (! $campaign->use_advanced_distribution || ! $config) {
            $deliveries = $campaign->deliveries()
                ->where('status', 'active')
                ->where('advanced_distribution_only', false)
                ->orderBy('priority')
                ->get();

            foreach ($deliveries as $delivery) {
                $steps[] = $this->stepForDelivery($lead, $delivery, 'standard');
            }

            return [
                'mode' => 'standard',
                'config_name' => null,
                'steps' => $steps,
                'would_sell' => collect($steps)->contains(fn ($s) => $s['eligible']),
            ];
        }

        foreach ($config->config['groups'] ?? [] as $index => $group) {
            $deliveryIds = $group['delivery_ids'] ?? [];
            $deliveries = Delivery::whereIn('id', $deliveryIds)->where('status', 'active')->get();
            $mode = $group['mode'] ?? 'waterfall';

            $tierSteps = [];
            foreach ($deliveries as $delivery) {
                $tierSteps[] = $this->stepForDelivery($lead, $delivery, $mode, $index + 1, $group['name'] ?? null);
            }

            $steps[] = [
                'type' => 'tier',
                'tier' => $index + 1,
                'name' => $group['name'] ?? 'Tier '.($index + 1),
                'mode' => $mode,
                'floor_price' => $group['floor_price'] ?? null,
                'deliveries' => $tierSteps,
                'would_win' => match (RoutingMode::tryFrom($mode)) {
                    RoutingMode::ParallelAuction => collect($tierSteps)->where('eligible', true)->isNotEmpty(),
                    default => collect($tierSteps)->firstWhere('eligible', true) !== null,
                },
            ];
        }

        return [
            'mode' => 'advanced',
            'config_name' => $config->name,
            'steps' => $steps,
            'would_sell' => collect($steps)->contains(fn ($t) => $t['would_win'] ?? false),
        ];
    }

    protected function stepForDelivery(Lead $lead, Delivery $delivery, string $routingContext, ?int $tier = null, ?string $groupName = null): array
    {
        $reasons = [];

        if ($delivery->status !== 'active') {
            $reasons[] = 'Delivery inactive';
        }

        if (! $this->capService->hasCapacity('delivery', $delivery->id, $delivery->caps)) {
            $reasons[] = 'Delivery cap reached';
        }

        if ($delivery->buyer_id && $delivery->buyer) {
            if (($delivery->buyer->status ?? 'active') !== 'active') {
                $reasons[] = 'Buyer inactive';
            }
            if (! app(BuyerBillingService::class)->hasCredit($delivery->buyer, (float) $delivery->revenue_amount)) {
                $reasons[] = 'Insufficient buyer credit';
            }
        }

        return [
            'delivery_id' => $delivery->id,
            'delivery_name' => $delivery->name,
            'method' => $delivery->method->value,
            'buyer' => $delivery->buyer?->name,
            'priority' => $delivery->priority,
            'weight' => $delivery->weight,
            'tier' => $tier,
            'group' => $groupName,
            'routing_context' => $routingContext,
            'revenue_type' => $delivery->revenue_type,
            'revenue_amount' => (float) $delivery->revenue_amount,
            'estimated_revenue' => $this->estimateRevenue($delivery),
            'eligible' => empty($reasons),
            'skip_reasons' => $reasons,
        ];
    }

    protected function estimateRevenue(Delivery $delivery): float
    {
        if ($delivery->revenue_type === 'dynamic') {
            $hint = (float) ($delivery->config['bid_hint'] ?? $delivery->revenue_amount);

            return round(max($hint, (float) $delivery->revenue_amount), 2);
        }

        return (float) $delivery->revenue_amount;
    }

    protected function syntheticLead(Campaign $campaign, array $fieldData): Lead
    {
        return new Lead([
            'campaign_id' => $campaign->id,
            'account_id' => $campaign->account_id,
            'field_data' => $fieldData,
            'status' => 'pending',
            'received_at' => now(),
        ]);
    }
}
