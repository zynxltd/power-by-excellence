<?php

namespace App\Services\Distribution;

use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Services\Caps\CapService;

class DistributionCapUsageService
{
    public function __construct(
        protected CapService $capService,
    ) {}

    /**
     * @param  iterable<int>  $deliveryIds
     * @return array<int, array{daily: array{used: int, limit: int|null}, hourly: array{used: int, limit: int|null}}>
     */
    public function forDeliveryIds(iterable $deliveryIds): array
    {
        $ids = collect($deliveryIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $deliveries = Delivery::query()
            ->whereIn('id', $ids)
            ->get(['id', 'caps']);

        $usage = [];

        foreach ($deliveries as $delivery) {
            $usage[$delivery->id] = $this->capService->usageForEntity(
                'delivery',
                $delivery->id,
                $delivery->caps,
            );
        }

        return $usage;
    }

    /**
     * @return array<int, array{daily: array{used: int, limit: int|null}, hourly: array{used: int, limit: int|null}}>
     */
    public function forDistribution(DistributionConfig $distribution): array
    {
        $deliveryIds = collect($distribution->config['groups'] ?? [])
            ->flatMap(fn (array $group) => $group['delivery_ids'] ?? [])
            ->unique()
            ->values();

        return $this->forDeliveryIds($deliveryIds);
    }
}
