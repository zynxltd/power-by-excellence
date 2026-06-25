<?php

namespace App\Services\Platform;

use App\Models\DeliveryLog;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;

class TenantHealth
{
    public function __construct(
        protected ProcessingMetrics $processingMetrics,
    ) {}

    public function status(int $accountId): string
    {
        $posts = $this->deliveryLogsForAccount($accountId)
            ->whereDate('delivery_logs.created_at', today())
            ->whereNotNull('delivery_logs.post_request')
            ->count();

        $postSuccess = $this->deliveryLogsForAccount($accountId)
            ->whereDate('delivery_logs.created_at', today())
            ->whereNotNull('delivery_logs.post_request')
            ->where('delivery_logs.status', 'success')
            ->count();

        $leadsToday = Lead::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->whereDate('received_at', today())
            ->count();

        $soldToday = Lead::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->whereDate('distributed_at', today())
            ->where('status', 'sold')
            ->count();

        $pingsToday = $this->deliveryLogsForAccount($accountId)
            ->whereDate('delivery_logs.created_at', today())
            ->whereNotNull('delivery_logs.ping_request')
            ->count();

        if ($pingsToday === 0 && $leadsToday === 0) {
            return 'idle';
        }

        $score = 100;

        if ($posts > 0) {
            $postRate = $postSuccess / $posts;
            if ($postRate < 0.4) {
                $score -= 40;
            } elseif ($postRate < 0.65) {
                $score -= 20;
            }
        }

        if ($leadsToday >= 5) {
            $soldRate = $soldToday / $leadsToday;
            if ($soldRate < 0.25) {
                $score -= 30;
            } elseif ($soldRate < 0.45) {
                $score -= 15;
            }
        }

        if (! $this->processingMetrics->withinTarget($accountId)) {
            $score -= 25;
        }

        return match (true) {
            $score >= 80 => 'healthy',
            $score >= 55 => 'warning',
            default => 'critical',
        };
    }

    /**
     * @return array{healthy: int, warning: int, critical: int, idle: int}
     */
    public function summarize(iterable $accountIds): array
    {
        $summary = ['healthy' => 0, 'warning' => 0, 'critical' => 0, 'idle' => 0];

        foreach ($accountIds as $id) {
            $summary[$this->status($id)]++;
        }

        return $summary;
    }

    protected function deliveryLogsForAccount(int $accountId): Builder
    {
        return DeliveryLog::query()
            ->join('deliveries', 'deliveries.id', '=', 'delivery_logs.delivery_id')
            ->join('campaigns', 'campaigns.id', '=', 'deliveries.campaign_id')
            ->where('campaigns.account_id', $accountId);
    }
}
