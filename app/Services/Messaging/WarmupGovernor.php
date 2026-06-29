<?php

namespace App\Services\Messaging;

use App\Models\MessageSend;
use App\Models\SendingProfile;

class WarmupGovernor extends ThrottleGovernor
{
    public const DEFAULT_DAY_ONE_LIMIT = 50;

    public const DEFAULT_TARGET_LIMIT = 1000;

    public const DEFAULT_RAMP_DAYS = 14;

    public function allowSend(int $accountId, ?SendingProfile $profile = null): bool
    {
        if (! parent::allowSend($accountId)) {
            return false;
        }

        $profile ??= $this->defaultWarmupProfile($accountId);

        if ($profile?->warmup_enabled) {
            return $this->remainingDailySends($profile) > 0;
        }

        return true;
    }

    public function currentWarmupDay(SendingProfile $profile): int
    {
        if (! $profile->warmup_started_at) {
            return 1;
        }

        return max(1, (int) $profile->warmup_started_at->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1);
    }

    public function dailyLimitForProfile(SendingProfile $profile): int
    {
        $day = $this->currentWarmupDay($profile);
        $start = max(1, (int) ($profile->warmup_day_one_limit ?: self::DEFAULT_DAY_ONE_LIMIT));
        $target = max($start, (int) ($profile->warmup_target_limit ?: self::DEFAULT_TARGET_LIMIT));
        $rampDays = max(1, (int) ($profile->warmup_ramp_days ?: self::DEFAULT_RAMP_DAYS));

        if ($day >= $rampDays) {
            return $target;
        }

        if ($rampDays === 1) {
            return $target;
        }

        $progress = ($day - 1) / ($rampDays - 1);

        return (int) round($start + ($target - $start) * $progress);
    }

    public function sendsTodayForProfile(SendingProfile $profile): int
    {
        return MessageSend::withoutGlobalScopes()
            ->where('account_id', $profile->account_id)
            ->where('sending_profile_id', $profile->id)
            ->where('channel', 'email')
            ->where('status', 'sent')
            ->whereDate('sent_at', today())
            ->count();
    }

    public function remainingDailySends(SendingProfile $profile): int
    {
        return max(0, $this->dailyLimitForProfile($profile) - $this->sendsTodayForProfile($profile));
    }

    /**
     * @return array<string, mixed>
     */
    public function warmupStatusForProfile(SendingProfile $profile): array
    {
        $dailyLimit = $this->dailyLimitForProfile($profile);
        $sentToday = $this->sendsTodayForProfile($profile);
        $warmupDay = $this->currentWarmupDay($profile);
        $rampDays = max(1, (int) ($profile->warmup_ramp_days ?: self::DEFAULT_RAMP_DAYS));
        $target = max(
            (int) ($profile->warmup_day_one_limit ?: self::DEFAULT_DAY_ONE_LIMIT),
            (int) ($profile->warmup_target_limit ?: self::DEFAULT_TARGET_LIMIT),
        );

        return [
            'profile_id' => $profile->id,
            'name' => $profile->name,
            'domain_match' => $profile->domain_match,
            'warmup_enabled' => (bool) $profile->warmup_enabled,
            'warmup_started_at' => $profile->warmup_started_at?->toIso8601String(),
            'warmup_day' => $warmupDay,
            'ramp_days' => $rampDays,
            'daily_limit' => $dailyLimit,
            'sent_today' => $sentToday,
            'remaining_today' => max(0, $dailyLimit - $sentToday),
            'target_limit' => $target,
            'day_one_limit' => (int) ($profile->warmup_day_one_limit ?: self::DEFAULT_DAY_ONE_LIMIT),
            'progress_pct' => $rampDays > 0 ? min(100, round(($warmupDay / $rampDays) * 100, 1)) : 100,
            'at_target' => $warmupDay >= $rampDays,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accountWarmupOverview(int $accountId): array
    {
        $profiles = SendingProfile::query()
            ->where('account_id', $accountId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $statuses = $profiles
            ->map(fn (SendingProfile $profile) => $this->warmupStatusForProfile($profile))
            ->values();

        $active = $statuses->first(fn (array $row) => $row['warmup_enabled']);

        return [
            'profiles' => $statuses->all(),
            'active_profile_id' => $active['profile_id'] ?? null,
            'has_warmup_enabled' => $statuses->contains(fn (array $row) => $row['warmup_enabled']),
        ];
    }

    public function reputationScore(int $accountId, int $days = 30): array
    {
        $summary = app(DeliverabilityReportService::class)->summary($accountId, $days);
        $totalSent = (int) ($summary['total_sent'] ?? 0);

        if ($totalSent === 0) {
            return [
                'score' => null,
                'label' => 'No sends yet',
                'period_days' => $days,
                'factors' => [],
            ];
        }

        $bounceRate = (float) ($summary['bounce_rate'] ?? 0);
        $complaintRate = (float) ($summary['complaint_rate'] ?? 0);
        $openRate = (float) ($summary['open_rate'] ?? 0);

        $bouncePenalty = min(45, $bounceRate * 5);
        $complaintPenalty = min(50, $complaintRate * 200);
        $openBonus = min(15, $openRate / 4);

        $score = (int) round(max(0, min(100, 100 - $bouncePenalty - $complaintPenalty + $openBonus)));

        $label = match (true) {
            $score >= 85 => 'Excellent',
            $score >= 70 => 'Good',
            $score >= 50 => 'Fair',
            default => 'Poor',
        };

        return [
            'score' => $score,
            'label' => $label,
            'period_days' => $days,
            'factors' => [
                'bounce_rate' => $bounceRate,
                'complaint_rate' => $complaintRate,
                'open_rate' => $openRate,
                'bounce_penalty' => round($bouncePenalty, 1),
                'complaint_penalty' => round($complaintPenalty, 1),
                'open_bonus' => round($openBonus, 1),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(int $accountId): array
    {
        return array_merge(parent::status($accountId), [
            'warmup' => $this->accountWarmupOverview($accountId),
            'reputation' => $this->reputationScore($accountId),
        ]);
    }

    public function ensureWarmupStarted(SendingProfile $profile): SendingProfile
    {
        if (! $profile->warmup_enabled || $profile->warmup_started_at) {
            return $profile;
        }

        $profile->update(['warmup_started_at' => now()]);

        return $profile->fresh();
    }

    protected function defaultWarmupProfile(int $accountId): ?SendingProfile
    {
        return SendingProfile::query()
            ->where('account_id', $accountId)
            ->where('warmup_enabled', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
