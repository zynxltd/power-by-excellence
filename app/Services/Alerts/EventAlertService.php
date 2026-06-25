<?php

namespace App\Services\Alerts;

use App\Models\EventAlert;
use App\Models\EventAlertFire;
use App\Models\Lead;
use App\Services\Logging\PlatformLogger;
use App\Services\Messaging\MessagingGateway;
use App\Services\Platform\ProcessingMetrics;
use Illuminate\Support\Facades\DB;

class EventAlertService
{
    public function __construct(
        protected MessagingGateway $messaging,
    ) {}

    public function evaluateForAccount(int $accountId): void
    {
        $alerts = EventAlert::where('account_id', $accountId)->where('status', 'active')->get();

        foreach ($alerts as $alert) {
            $value = $this->metricValue($alert->metric, $accountId);
            if ($this->triggered($alert, $value)) {
                $this->fire($alert, $value);
            }
        }
    }

    public function evaluateAfterLead(Lead $lead): void
    {
        $this->evaluateForAccount($lead->account_id);
    }

    protected function metricValue(string $metric, int $accountId): float
    {
        return match ($metric) {
            'leads_today' => (float) Lead::withoutGlobalScopes()->where('account_id', $accountId)->whereDate('received_at', today())->count(),
            'sold_today' => (float) Lead::withoutGlobalScopes()->where('account_id', $accountId)->whereDate('distributed_at', today())->where('status', 'sold')->count(),
            'unsold_today' => (float) Lead::withoutGlobalScopes()->where('account_id', $accountId)->whereDate('received_at', today())->where('status', 'unsold')->count(),
            'reject_rate_24h' => $this->rejectRate($accountId),
            'delivery_success_rate_24h' => $this->deliverySuccessRate($accountId),
            'pending_queue' => (float) Lead::withoutGlobalScopes()->where('account_id', $accountId)->whereIn('status', ['pending', 'processing'])->count(),
            'quarantined_count' => (float) Lead::withoutGlobalScopes()->where('account_id', $accountId)->where('status', 'quarantined')->count(),
            'avg_processing_ms_24h' => $this->avgProcessingMs($accountId),
            'caps_near_limit' => $this->capsNearLimit($accountId),
            default => 0,
        };
    }

    protected function rejectRate(int $accountId): float
    {
        $since = now()->subDay();
        $total = Lead::withoutGlobalScopes()->where('account_id', $accountId)->where('received_at', '>=', $since)->count();
        if ($total === 0) {
            return 0;
        }
        $rejected = Lead::withoutGlobalScopes()->where('account_id', $accountId)->where('received_at', '>=', $since)->where('status', 'rejected')->count();

        return round(($rejected / $total) * 100, 1);
    }

    protected function deliverySuccessRate(int $accountId): float
    {
        $since = now()->subDay();
        $total = DB::table('delivery_logs')
            ->join('deliveries', 'deliveries.id', '=', 'delivery_logs.delivery_id')
            ->where('deliveries.account_id', $accountId)
            ->where('delivery_logs.created_at', '>=', $since)
            ->count();
        if ($total === 0) {
            return 100;
        }
        $success = DB::table('delivery_logs')
            ->join('deliveries', 'deliveries.id', '=', 'delivery_logs.delivery_id')
            ->where('deliveries.account_id', $accountId)
            ->where('delivery_logs.created_at', '>=', $since)
            ->where('delivery_logs.status', 'success')
            ->count();

        return round(($success / $total) * 100, 1);
    }

    protected function avgProcessingMs(int $accountId): float
    {
        return app(ProcessingMetrics::class)->avgProcessingMs($accountId);
    }

    protected function capsNearLimit(int $accountId): float
    {
        $buyers = DB::table('buyers')->where('account_id', $accountId)->where('status', 'active')->get(['id', 'caps']);
        $near = 0;
        foreach ($buyers as $buyer) {
            $caps = json_decode($buyer->caps ?? '{}', true);
            $daily = $caps['daily'] ?? null;
            if (! $daily) {
                continue;
            }
            $used = DB::table('delivery_logs')
                ->where('buyer_id', $buyer->id)
                ->whereDate('created_at', today())
                ->where('status', 'success')
                ->count();
            if ($used / max(1, $daily) >= 0.9) {
                $near++;
            }
        }

        return (float) $near;
    }

    protected function triggered(EventAlert $alert, float $value): bool
    {
        return match ($alert->operator) {
            'gt' => $value > (float) $alert->threshold,
            'gte' => $value >= (float) $alert->threshold,
            'eq' => $value === (float) $alert->threshold,
            'lte' => $value <= (float) $alert->threshold,
            default => $value < (float) $alert->threshold,
        };
    }

    protected function fire(EventAlert $alert, float $value): void
    {
        $cooldownMinutes = (int) ($alert->config['cooldown_minutes'] ?? 60);
        if ($alert->last_triggered_at && $alert->last_triggered_at->gt(now()->subMinutes($cooldownMinutes))) {
            return;
        }

        $message = "Alert \"{$alert->name}\": {$alert->metric} = {$value} (threshold {$alert->operator} {$alert->threshold})";
        PlatformLogger::info('event_alert.triggered', ['alert_id' => $alert->id, 'value' => $value]);

        $status = 'sent';
        $config = $alert->config ?? [];

        try {
            if ($alert->channel === 'email') {
                $to = $config['email'] ?? config('mail.from.address');
                if ($to) {
                    $this->messaging->sendEmail($to, "[Alert] {$alert->name}", $message, [
                        'provider' => $config['provider'] ?? null,
                    ]);
                }
            } elseif ($alert->channel === 'sms') {
                $to = $config['phone'] ?? null;
                if ($to) {
                    $this->messaging->sendSms($to, $message, [
                        'provider' => $config['provider'] ?? null,
                    ]);
                }
            } elseif ($alert->channel === 'webhook') {
                $url = $config['webhook_url'] ?? null;
                if ($url) {
                    $ok = $this->messaging->sendWebhook($url, $message);
                    $status = $ok ? 'sent' : 'failed';
                }
            } elseif ($alert->channel === 'slack') {
                $url = $config['slack_webhook'] ?? null;
                if ($url) {
                    $ok = $this->messaging->sendWebhook($url, $message);
                    $status = $ok ? 'sent' : 'failed';
                }
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            PlatformLogger::error('Event alert delivery failed', ['alert_id' => $alert->id], null, $e);
        }

        EventAlertFire::create([
            'event_alert_id' => $alert->id,
            'account_id' => $alert->account_id,
            'metric' => $alert->metric,
            'value' => $value,
            'threshold' => $alert->threshold,
            'channel' => $alert->channel,
            'status' => $status,
            'message' => $message,
        ]);

        $alert->update(['last_triggered_at' => now()]);
    }
}
