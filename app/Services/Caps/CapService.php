<?php

namespace App\Services\Caps;

use App\Models\CapCounter;
use Carbon\Carbon;

class CapService
{
    public function hasCapacity(string $entityType, int $entityId, ?array $caps): bool
    {
        if (empty($caps)) {
            return true;
        }

        foreach ($this->periods($caps) as $period => $limit) {
            if ($limit <= 0) {
                continue;
            }

            $key = $this->periodKey($period);
            $counter = CapCounter::query()
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->where('period', $period)
                ->where('period_key', $key)
                ->value('count') ?? 0;

            if ($counter >= $limit) {
                return false;
            }
        }

        return true;
    }

    public function increment(string $entityType, int $entityId, ?array $caps): void
    {
        if (empty($caps)) {
            return;
        }

        foreach ($this->periods($caps) as $period => $limit) {
            if ($limit <= 0) {
                continue;
            }

            $key = $this->periodKey($period);

            CapCounter::query()->updateOrCreate(
                [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'period' => $period,
                    'period_key' => $key,
                ],
                [
                    'reset_at' => $this->resetAt($period),
                ]
            )->increment('count');
        }
    }

    public function hasSpendCapacity(string $entityType, int $entityId, ?array $caps, float $amount): bool
    {
        if (empty($caps) || $amount <= 0) {
            return true;
        }

        foreach ($this->spendPeriods($caps) as $period => $limit) {
            if ($limit <= 0) {
                continue;
            }

            $spent = (float) CapCounter::query()
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->where('period', $period)
                ->where('period_key', $this->periodKey($this->spendPeriodMap()[$period]))
                ->value('spend') ?? 0;

            if ($spent + $amount > $limit) {
                return false;
            }
        }

        return true;
    }

    public function incrementSpend(string $entityType, int $entityId, ?array $caps, float $amount): void
    {
        if (empty($caps) || $amount <= 0) {
            return;
        }

        foreach ($this->spendPeriods($caps) as $period => $limit) {
            if ($limit <= 0) {
                continue;
            }

            $volumePeriod = $this->spendPeriodMap()[$period];
            $key = $this->periodKey($volumePeriod);

            $counter = CapCounter::query()->firstOrCreate(
                [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'period' => $period,
                    'period_key' => $key,
                ],
                [
                    'reset_at' => $this->resetAt($volumePeriod),
                    'count' => 0,
                    'spend' => 0,
                ]
            );

            $counter->increment('spend', $amount);
        }
    }

    protected function periods(array $caps): array
    {
        $map = [];
        foreach (['hourly', 'daily', 'weekly', 'monthly', 'total'] as $period) {
            if (isset($caps[$period]) && (int) $caps[$period] > 0) {
                $map[$period] = (int) $caps[$period];
            }
        }

        return $map;
    }

    /**
     * @return array<string, float>
     */
    protected function spendPeriods(array $caps): array
    {
        $map = [];
        foreach ([
            'daily_spend_cap' => 'daily',
            'weekly_spend_cap' => 'weekly',
            'monthly_spend_cap' => 'monthly',
        ] as $capKey => $volumePeriod) {
            if (isset($caps[$capKey]) && (float) $caps[$capKey] > 0) {
                $map['spend_'.$volumePeriod] = (float) $caps[$capKey];
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    protected function spendPeriodMap(): array
    {
        return [
            'spend_daily' => 'daily',
            'spend_weekly' => 'weekly',
            'spend_monthly' => 'monthly',
        ];
    }

    protected function periodKey(string $period): string
    {
        return match ($period) {
            'hourly' => now()->format('Y-m-d-H'),
            'daily' => now()->format('Y-m-d'),
            'weekly' => now()->format('o-W'),
            'monthly' => now()->format('Y-m'),
            'total' => 'all',
            default => 'all',
        };
    }

    protected function resetAt(string $period): ?Carbon
    {
        return match ($period) {
            'hourly' => now()->endOfHour(),
            'daily' => now()->endOfDay(),
            'weekly' => now()->endOfWeek(),
            'monthly' => now()->endOfMonth(),
            default => null,
        };
    }
}
