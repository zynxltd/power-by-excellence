<?php

namespace App\Services\Messaging;

use App\Models\MessageEvent;
use App\Models\MessageSend;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeliverabilityReportService
{
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
