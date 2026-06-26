<?php

namespace App\Services\Delivery;

use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Services\Billing\BuyerBillingService;
use App\Services\Caps\CapService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeliveryAnalyticsService
{
    /**
     * @return array<int, array{last_24h_total: int, last_24h_success: int, success_rate: ?float, avg_revenue: float, avg_duration_ms: float}>
     */
    public function bulkStatsFor(array $deliveryIds): array
    {
        if ($deliveryIds === []) {
            return [];
        }

        $since = now()->subDay();

        $rows = DB::table('delivery_logs')
            ->whereIn('delivery_id', $deliveryIds)
            ->where('created_at', '>=', $since)
            ->groupBy('delivery_id')
            ->selectRaw("
                delivery_id,
                count(*) as last_24h_total,
                sum(case when status = 'success' then 1 else 0 end) as last_24h_success,
                avg(case when status = 'success' then revenue end) as avg_revenue,
                avg(duration_ms) as avg_duration_ms
            ")
            ->get();

        $stats = [];
        foreach ($deliveryIds as $id) {
            $stats[$id] = [
                'last_24h_total' => 0,
                'last_24h_success' => 0,
                'success_rate' => null,
                'avg_revenue' => 0.0,
                'avg_duration_ms' => 0.0,
            ];
        }

        foreach ($rows as $row) {
            $total = (int) $row->last_24h_total;
            $success = (int) $row->last_24h_success;
            $stats[(int) $row->delivery_id] = [
                'last_24h_total' => $total,
                'last_24h_success' => $success,
                'success_rate' => $total > 0 ? round(($success / $total) * 100, 1) : null,
                'avg_revenue' => round((float) ($row->avg_revenue ?? 0), 2),
                'avg_duration_ms' => round((float) ($row->avg_duration_ms ?? 0), 0),
            ];
        }

        return $stats;
    }

    public function statsFor(Delivery $delivery): array
    {
        return $this->bulkStatsFor([$delivery->id])[$delivery->id]
            ?? [
                'last_24h_total' => 0,
                'last_24h_success' => 0,
                'success_rate' => null,
                'avg_revenue' => 0.0,
                'avg_duration_ms' => 0.0,
            ];
    }

    public function healthFor(Delivery $delivery, ?array $stats = null): string
    {
        if ($delivery->status !== 'active') {
            return 'inactive';
        }

        if ($delivery->buyer_id && $delivery->buyer) {
            if (($delivery->buyer->status ?? 'active') !== 'active') {
                return 'critical';
            }
            if (! app(BuyerBillingService::class)->hasCredit($delivery->buyer, (float) $delivery->revenue_amount)) {
                return 'critical';
            }
        }

        if ($delivery->caps && ! app(CapService::class)->hasCapacity('delivery', $delivery->id, $delivery->caps)) {
            return 'warning';
        }

        $stats ??= $this->statsFor($delivery);
        if ($stats['last_24h_total'] > 0 && ($stats['success_rate'] ?? 100) < 50) {
            return 'warning';
        }

        if ($delivery->advanced_distribution_only && ! $this->isInPingTree($delivery)) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * @return array{healthy: int, warning: int, critical: int, inactive: int}
     */
    public function healthCountsFor(Collection $deliveries): array
    {
        $counts = ['healthy' => 0, 'warning' => 0, 'critical' => 0, 'inactive' => 0];
        if ($deliveries->isEmpty()) {
            return $counts;
        }

        $bulkStats = $this->bulkStatsFor($deliveries->pluck('id')->all());

        foreach ($deliveries as $delivery) {
            $health = $this->healthFor($delivery, $bulkStats[$delivery->id] ?? null);
            $counts[$health] = ($counts[$health] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array{health: string, stats: array<string, mixed>}
     */
    public function enrichDelivery(Delivery $delivery, ?array $stats = null): array
    {
        $array = $delivery->toArray();
        $stats ??= $this->bulkStatsFor([$delivery->id])[$delivery->id] ?? $this->statsFor($delivery);
        $array['stats'] = $stats;
        $array['health'] = $this->healthFor($delivery, $stats);

        return $array;
    }

    public function isInPingTree(Delivery $delivery): bool
    {
        return DistributionConfig::where('campaign_id', $delivery->campaign_id)
            ->get()
            ->contains(function (DistributionConfig $config) use ($delivery) {
                foreach ($config->config['groups'] ?? [] as $group) {
                    if (in_array($delivery->id, $group['delivery_ids'] ?? [], true)) {
                        return true;
                    }
                }

                return false;
            });
    }

    public function pingTreeLinks(Delivery $delivery): array
    {
        return DistributionConfig::where('campaign_id', $delivery->campaign_id)
            ->get()
            ->flatMap(function (DistributionConfig $config) use ($delivery) {
                $links = [];
                foreach ($config->config['groups'] ?? [] as $index => $group) {
                    if (in_array($delivery->id, $group['delivery_ids'] ?? [], true)) {
                        $links[] = [
                            'config_id' => $config->id,
                            'config_name' => $config->name,
                            'tier' => $index + 1,
                            'group_name' => $group['name'] ?? 'Tier '.($index + 1),
                            'mode' => $group['mode'] ?? 'waterfall',
                        ];
                    }
                }

                return $links;
            })
            ->values()
            ->all();
    }

    public function platformSummary(): array
    {
        return [
            'deliveries_total' => Delivery::count(),
            'deliveries_active' => Delivery::where('status', 'active')->count(),
            'logs_today' => DB::table('delivery_logs')->whereDate('created_at', today())->count(),
            'success_today' => DB::table('delivery_logs')->whereDate('created_at', today())->where('status', 'success')->count(),
        ];
    }
}
