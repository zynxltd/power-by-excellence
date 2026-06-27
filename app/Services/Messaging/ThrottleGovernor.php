<?php

namespace App\Services\Messaging;

use App\Models\MessageEvent;
use App\Models\MessageSend;
use Illuminate\Support\Facades\Cache;

class ThrottleGovernor
{
    public function allowSend(int $accountId): bool
    {
        if ($this->bounceRateExceeded($accountId)) {
            return false;
        }

        return true;
    }

    public function bounceRateExceeded(int $accountId, int $windowMinutes = 15, float $threshold = 0.15): bool
    {
        $cacheKey = "messaging.throttle.{$accountId}";

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
            Cache::put($cacheKey, 'paused', now()->addMinutes(5));

            return true;
        }

        return false;
    }

    public function chunkDelay(int $accountId, ?int $throttlePerMinute): int
    {
        $rate = $throttlePerMinute ?: 100;

        return (int) max(1, ceil(60 / max($rate, 1)));
    }
}
