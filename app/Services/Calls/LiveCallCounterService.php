<?php

namespace App\Services\Calls;

use Illuminate\Support\Facades\Cache;

class LiveCallCounterService
{
    private const TTL_HOURS = 24;

    public function markInProgress(int $accountId, string $callSid): int
    {
        return $this->mutate($accountId, function (array $active) use ($callSid): array {
            if (! in_array($callSid, $active, true)) {
                $active[] = $callSid;
            }

            return $active;
        });
    }

    public function markCompleted(int $accountId, string $callSid): int
    {
        return $this->mutate($accountId, function (array $active) use ($callSid): array {
            return array_values(array_filter(
                $active,
                fn (string $sid): bool => $sid !== $callSid,
            ));
        });
    }

    public function countForAccount(int $accountId): int
    {
        return count($this->activeSids($accountId));
    }

    /**
     * @return list<string>
     */
    private function activeSids(int $accountId): array
    {
        $active = Cache::get($this->cacheKey($accountId), []);

        return is_array($active) ? array_values($active) : [];
    }

    private function cacheKey(int $accountId): string
    {
        return "call_logic:live_calls:{$accountId}";
    }

    /**
     * @param  callable(list<string>): list<string>  $callback
     */
    private function mutate(int $accountId, callable $callback): int
    {
        $active = $callback($this->activeSids($accountId));
        Cache::put($this->cacheKey($accountId), $active, now()->addHours(self::TTL_HOURS));

        return count($active);
    }
}
