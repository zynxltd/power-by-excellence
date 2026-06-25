<?php

namespace App\Services\Delivery;

use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Services\Billing\BuyerBillingService;
use App\Services\Caps\CapService;
use Illuminate\Support\Facades\DB;

class DeliveryAnalyticsService
{
    public function statsFor(Delivery $delivery): array
    {
        $since = now()->subDay();

        $logs = $delivery->logs()->where('created_at', '>=', $since);
        $total = (clone $logs)->count();
        $success = (clone $logs)->where('status', 'success')->count();

        return [
            'last_24h_total' => $total,
            'last_24h_success' => $success,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 1) : null,
            'avg_revenue' => round((float) (clone $logs)->where('status', 'success')->avg('revenue'), 2),
            'avg_duration_ms' => round((float) (clone $logs)->avg('duration_ms'), 0),
        ];
    }

    public function healthFor(Delivery $delivery): string
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

        $stats = $this->statsFor($delivery);
        if ($stats['last_24h_total'] > 0 && ($stats['success_rate'] ?? 100) < 50) {
            return 'warning';
        }

        if ($delivery->advanced_distribution_only && ! $this->isInPingTree($delivery)) {
            return 'warning';
        }

        return 'healthy';
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
