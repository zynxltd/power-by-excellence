<?php

namespace App\Services\Messaging;

use App\Models\BulkSmsCampaign;
use App\Models\MessageEvent;
use App\Models\MessageSend;
use Illuminate\Support\Facades\Cache;

class ThrottleGovernor
{
    public const BOUNCE_WINDOW_MINUTES = 15;

    public const BOUNCE_THRESHOLD = 0.15;

    public const PAUSE_MINUTES = 5;

    public const DEFAULT_SEND_RATE_PER_MINUTE = 100;

    public const MANUAL_PAUSE_TTL_MINUTES = 525600;

    public function allowSend(int $accountId, ?\App\Models\SendingProfile $profile = null): bool
    {
        if ($this->isManuallyPaused($accountId)) {
            return false;
        }

        if ($this->bounceRateExceeded($accountId)) {
            return false;
        }

        return true;
    }

    public function pauseSending(int $accountId, ?int $minutes = null): void
    {
        Cache::put(
            $this->manualCacheKey($accountId),
            true,
            now()->addMinutes($minutes ?? self::MANUAL_PAUSE_TTL_MINUTES),
        );
    }

    public function resumeSending(int $accountId): void
    {
        Cache::forget($this->manualCacheKey($accountId));
        Cache::forget($this->autoCacheKey($accountId));
    }

    public function isManuallyPaused(int $accountId): bool
    {
        return Cache::get($this->manualCacheKey($accountId)) === true;
    }

    public function isAutoPaused(int $accountId): bool
    {
        return Cache::get($this->autoCacheKey($accountId)) === 'paused';
    }

    public function bounceRateExceeded(int $accountId, int $windowMinutes = self::BOUNCE_WINDOW_MINUTES, float $threshold = self::BOUNCE_THRESHOLD): bool
    {
        $cacheKey = $this->autoCacheKey($accountId);

        if (Cache::get($cacheKey) === 'paused') {
            return true;
        }

        $since = now()->subMinutes($windowMinutes);

        $sent = MessageSend::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('channel', 'email')
            ->where('sent_at', '>=', $since)
            ->count();

        if ($sent < 10) {
            return false;
        }

        $bounces = MessageEvent::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('type', 'bounce')
            ->where('occurred_at', '>=', $since)
            ->count();

        $rate = $bounces / max($sent, 1);

        if ($rate >= $threshold) {
            Cache::put($cacheKey, 'paused', now()->addMinutes(self::PAUSE_MINUTES));

            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function status(int $accountId): array
    {
        $since = now()->subMinutes(self::BOUNCE_WINDOW_MINUTES);
        $manualPaused = $this->isManuallyPaused($accountId);
        $autoPaused = $this->isAutoPaused($accountId);
        $paused = $manualPaused || $autoPaused;

        $recentSent = MessageSend::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('channel', 'email')
            ->where('sent_at', '>=', $since)
            ->count();

        $recentBounces = MessageEvent::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('type', 'bounce')
            ->where('occurred_at', '>=', $since)
            ->count();

        $bounceRate = $recentSent > 0 ? round(($recentBounces / $recentSent) * 100, 2) : 0;

        $queuedCampaigns = BulkSmsCampaign::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->whereIn('status', ['scheduled', 'queued', 'sending'])
            ->get(['id', 'name', 'status', 'scheduled_at', 'throttle_per_minute']);

        $pendingSends = MessageSend::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->whereIn('status', ['pending', 'scheduled'])
            ->count();

        $activeThrottle = (int) ($queuedCampaigns->max('throttle_per_minute') ?: self::DEFAULT_SEND_RATE_PER_MINUTE);

        return [
            'paused' => $paused,
            'manual_paused' => $manualPaused,
            'auto_paused' => $autoPaused,
            'paused_reason' => $manualPaused ? 'manual' : ($autoPaused ? 'bounce_rate' : null),
            'bounce_rate_recent' => $bounceRate,
            'bounce_threshold_pct' => self::BOUNCE_THRESHOLD * 100,
            'window_minutes' => self::BOUNCE_WINDOW_MINUTES,
            'pause_minutes' => self::PAUSE_MINUTES,
            'recent_sent' => $recentSent,
            'recent_bounces' => $recentBounces,
            'queued_campaigns' => $queuedCampaigns->count(),
            'queue_depth' => $queuedCampaigns->count() + $pendingSends,
            'queued_campaign_list' => $queuedCampaigns->map(fn (BulkSmsCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'status' => $c->status,
                'scheduled_at' => $c->scheduled_at?->toIso8601String(),
                'throttle_per_minute' => $c->throttle_per_minute,
            ])->values()->all(),
            'pending_sends' => $pendingSends,
            'default_rate_per_minute' => self::DEFAULT_SEND_RATE_PER_MINUTE,
            'active_rate_per_minute' => $activeThrottle,
            'chunk_delay_seconds' => $this->chunkDelay($accountId, $activeThrottle),
        ];
    }

    public function chunkDelay(int $accountId, ?int $throttlePerMinute): int
    {
        $rate = $throttlePerMinute ?: self::DEFAULT_SEND_RATE_PER_MINUTE;

        return (int) max(1, ceil(60 / max($rate, 1)));
    }

    protected function autoCacheKey(int $accountId): string
    {
        return "messaging.throttle.{$accountId}";
    }

    protected function manualCacheKey(int $accountId): string
    {
        return "messaging.throttle.manual.{$accountId}";
    }
}
