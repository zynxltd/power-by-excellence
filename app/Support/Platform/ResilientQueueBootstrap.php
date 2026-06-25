<?php

namespace App\Support\Platform;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ResilientQueueBootstrap
{
    public static function apply(): void
    {
        $preferred = (string) config('platform.queue.preferred_connection', 'database');
        $fallback = (string) config('platform.queue.fallback_connection', 'database');
        $allowFallback = (bool) config('platform.queue.redis_fallback', true);

        if (! self::expectsRedis($preferred)) {
            Config::set('queue.default', $preferred);

            return;
        }

        if (self::redisReachable()) {
            Config::set('queue.default', $preferred === 'failover' ? 'redis' : $preferred);

            return;
        }

        if (! $allowFallback) {
            return;
        }

        Config::set('queue.default', $fallback);
        Config::set('platform.queue.fallback_active', true);

        if (! app()->runningInConsole() || app()->runningUnitTests()) {
            return;
        }

        try {
            Log::channel('platform')->warning('Redis unavailable — queue driver fell back to database.', [
                'preferred' => $preferred,
                'fallback' => $fallback,
            ]);
        } catch (\Throwable) {
            // logging channel may not be ready
        }
    }

    protected static function expectsRedis(string $connection): bool
    {
        return in_array($connection, ['redis', 'failover'], true);
    }

    protected static function redisReachable(): bool
    {
        try {
            $pong = Redis::connection()->ping();

            return strtoupper((string) $pong) === 'PONG' || $pong === true;
        } catch (\Throwable) {
            return false;
        }
    }
}
