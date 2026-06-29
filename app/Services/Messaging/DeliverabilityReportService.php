<?php

namespace App\Services\Messaging;

use App\Models\Account;
use App\Models\MessageEvent;
use App\Models\MessageSend;
use App\Models\MarketingOptOut;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeliverabilityReportService
{
    public const DEFAULT_BOUNCE_ALERT_PCT = 5.0;

    public const DEFAULT_COMPLAINT_ALERT_PCT = 0.1;

    /**
     * @return array<string, mixed>
     */
    public function opsCenter(int $accountId, ?Account $account = null): array
    {
        $account ??= Account::find($accountId);
        $summary7 = $this->summary($accountId, 7);
        $summary30 = $this->summary($accountId, 30);

        return [
            'summary_7d' => $summary7,
            'summary_30d' => $summary30,
            'suppression_count' => app(MarketingSuppressionService::class)->countForAccount($accountId),
            'alerts' => $this->alerts($accountId, $summary7, $summary30, $account),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function alerts(int $accountId, ?array $summary7 = null, ?array $summary30 = null, ?Account $account = null): array
    {
        $summary7 ??= $this->summary($accountId, 7);
        $summary30 ??= $this->summary($accountId, 30);
        $account ??= Account::find($accountId);
        $thresholds = $this->thresholds($account);

        $alerts = [];

        if (($summary7['total_sent'] ?? 0) > 0 && ($summary7['bounce_rate'] ?? 0) >= $thresholds['bounce_rate_alert_pct']) {
            $alerts[] = [
                'level' => 'warning',
                'metric' => 'bounce_rate_7d',
                'message' => "7-day bounce rate is {$summary7['bounce_rate']}% (threshold {$thresholds['bounce_rate_alert_pct']}%).",
                'value' => $summary7['bounce_rate'],
                'threshold' => $thresholds['bounce_rate_alert_pct'],
            ];
        }

        if (($summary30['total_sent'] ?? 0) > 0 && ($summary30['bounce_rate'] ?? 0) >= $thresholds['bounce_rate_alert_pct']) {
            $alerts[] = [
                'level' => 'warning',
                'metric' => 'bounce_rate_30d',
                'message' => "30-day bounce rate is {$summary30['bounce_rate']}% (threshold {$thresholds['bounce_rate_alert_pct']}%).",
                'value' => $summary30['bounce_rate'],
                'threshold' => $thresholds['bounce_rate_alert_pct'],
            ];
        }

        if (($summary7['total_sent'] ?? 0) > 0 && ($summary7['complaint_rate'] ?? 0) >= $thresholds['complaint_rate_alert_pct']) {
            $alerts[] = [
                'level' => 'critical',
                'metric' => 'complaint_rate_7d',
                'message' => "7-day complaint rate is {$summary7['complaint_rate']}% (threshold {$thresholds['complaint_rate_alert_pct']}%).",
                'value' => $summary7['complaint_rate'],
                'threshold' => $thresholds['complaint_rate_alert_pct'],
            ];
        }

        if (($summary30['total_sent'] ?? 0) > 0 && ($summary30['complaint_rate'] ?? 0) >= $thresholds['complaint_rate_alert_pct']) {
            $alerts[] = [
                'level' => 'critical',
                'metric' => 'complaint_rate_30d',
                'message' => "30-day complaint rate is {$summary30['complaint_rate']}% (threshold {$thresholds['complaint_rate_alert_pct']}%).",
                'value' => $summary30['complaint_rate'],
                'threshold' => $thresholds['complaint_rate_alert_pct'],
            ];
        }

        return $alerts;
    }

    /**
     * @return array{bounce_rate_alert_pct: float, complaint_rate_alert_pct: float}
     */
    public function thresholds(?Account $account): array
    {
        $deliverability = $account?->settings['messaging']['deliverability'] ?? [];

        return [
            'bounce_rate_alert_pct' => (float) ($deliverability['bounce_rate_alert_pct'] ?? self::DEFAULT_BOUNCE_ALERT_PCT),
            'complaint_rate_alert_pct' => (float) ($deliverability['complaint_rate_alert_pct'] ?? self::DEFAULT_COMPLAINT_ALERT_PCT),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $accountId, int $days = 30): array
    {
        $since = now()->subDays($days);

        $sends = MessageSend::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('sent_at', '>=', $since);

        $totalSent = (clone $sends)->count();
        $byProvider = (clone $sends)
            ->select('provider', DB::raw('count(*) as total'))
            ->groupBy('provider')
            ->pluck('total', 'provider');

        $byChannel = (clone $sends)
            ->select('channel', DB::raw('count(*) as total'))
            ->groupBy('channel')
            ->pluck('total', 'channel');

        $events = MessageEvent::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('occurred_at', '>=', $since);

        $opens = (clone $events)->where('type', 'open')->count();
        $clicks = (clone $events)->where('type', 'click')->count();
        $bounces = (clone $events)->where('type', 'bounce')->count();
        $complaints = (clone $events)->where('type', 'complaint')->count();
        $delivered = (clone $events)->where('type', 'delivered')->count();

        return [
            'total_sent' => $totalSent,
            'opens' => $opens,
            'clicks' => $clicks,
            'bounces' => $bounces,
            'complaints' => $complaints,
            'delivered' => $delivered,
            'open_rate' => $totalSent > 0 ? round(($opens / $totalSent) * 100, 2) : 0,
            'click_rate' => $totalSent > 0 ? round(($clicks / $totalSent) * 100, 2) : 0,
            'bounce_rate' => $totalSent > 0 ? round(($bounces / $totalSent) * 100, 2) : 0,
            'complaint_rate' => $totalSent > 0 ? round(($complaints / $totalSent) * 100, 2) : 0,
            'delivery_rate' => $totalSent > 0 ? round(($delivered / $totalSent) * 100, 2) : 0,
            'click_to_open_rate' => $opens > 0 ? round(($clicks / $opens) * 100, 2) : 0,
            'by_provider' => $byProvider,
            'by_channel' => $byChannel,
            'period_days' => $days,
        ];
    }

    /**
     * @return Collection<int, array{hour: int, opens: int}>
     */
    public function hourlyOpens(int $accountId, int $days = 30): Collection
    {
        $since = now()->subDays($days);

        return MessageEvent::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('type', 'open')
            ->where('occurred_at', '>=', $since)
            ->get(['occurred_at'])
            ->groupBy(fn (MessageEvent $event) => (int) $event->occurred_at->format('G'))
            ->map(fn ($group, $hour) => ['hour' => (int) $hour, 'opens' => $group->count()])
            ->sortBy('hour')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function campaignStats(int $accountId, int $limit = 20): Collection
    {
        return MessageSend::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->whereNotNull('bulk_sms_campaign_id')
            ->select('bulk_sms_campaign_id', DB::raw('count(*) as sent'))
            ->groupBy('bulk_sms_campaign_id')
            ->orderByDesc('sent')
            ->limit($limit)
            ->get()
            ->map(function ($row) use ($accountId) {
                $opens = MessageEvent::withoutGlobalScopes()
                    ->where('account_id', $accountId)
                    ->where('type', 'open')
                    ->whereHas('messageSend', fn ($q) => $q->where('bulk_sms_campaign_id', $row->bulk_sms_campaign_id))
                    ->count();

                $clicks = MessageEvent::withoutGlobalScopes()
                    ->where('account_id', $accountId)
                    ->where('type', 'click')
                    ->whereHas('messageSend', fn ($q) => $q->where('bulk_sms_campaign_id', $row->bulk_sms_campaign_id))
                    ->count();

                $sent = (int) $row->sent;

                return [
                    'bulk_sms_campaign_id' => $row->bulk_sms_campaign_id,
                    'sent' => $sent,
                    'opens' => $opens,
                    'clicks' => $clicks,
                    'open_rate' => $sent > 0 ? round(($opens / $sent) * 100, 2) : 0,
                    'click_rate' => $sent > 0 ? round(($clicks / $sent) * 100, 2) : 0,
                ];
            });
    }
}
