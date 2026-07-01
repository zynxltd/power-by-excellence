<?php

namespace App\Services\Leads;

use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Support\Queue\LeadJobDispatcher;
use Illuminate\Support\Carbon;

class LeadRepostService
{
    public function defaultConfig(): array
    {
        return [
            'enabled' => true,
            'max_attempts' => null,
            'min_age_minutes' => 0,
            'cooldown_minutes' => 0,
        ];
    }

    public function configFor(Campaign $campaign): array
    {
        return array_merge($this->defaultConfig(), $campaign->repost_config ?? []);
    }

    public function globalMaxAttempts(): int
    {
        return (int) config('platform.max_repost_attempts', 3);
    }

    public function maxAttempts(Campaign $campaign): int
    {
        $config = $this->configFor($campaign);
        if (isset($config['max_attempts']) && $config['max_attempts'] !== null && $config['max_attempts'] !== '') {
            return max(1, (int) $config['max_attempts']);
        }

        return $this->globalMaxAttempts();
    }

    /**
     * @return array{
     *     eligible: bool,
     *     reason: ?string,
     *     attempts: int,
     *     max_attempts: int,
     *     next_eligible_at: ?string,
     *     enabled: bool,
     * }
     */
    public function eligibility(Lead $lead): array
    {
        $lead->loadMissing('campaign');
        $campaign = $lead->campaign;
        $config = $campaign ? $this->configFor($campaign) : $this->defaultConfig();
        $attempts = (int) ($lead->metadata['repost_attempts'] ?? 0);
        $maxAttempts = $campaign ? $this->maxAttempts($campaign) : $this->globalMaxAttempts();

        $result = [
            'eligible' => false,
            'reason' => null,
            'attempts' => $attempts,
            'max_attempts' => $maxAttempts,
            'next_eligible_at' => null,
            'enabled' => (bool) ($config['enabled'] ?? true),
        ];

        if (! in_array($lead->status, [LeadStatus::Unsold, LeadStatus::Quarantined], true)) {
            $result['reason'] = 'Only unsold or quarantined leads can be reposted.';

            return $result;
        }

        if ($lead->status === LeadStatus::Quarantined && $this->isValidationQuarantine($lead)) {
            $result['reason'] = 'Validation holds must be released or rejected — not reposted.';

            return $result;
        }

        if (! ($config['enabled'] ?? true)) {
            $result['reason'] = 'Repost is disabled for this campaign.';

            return $result;
        }

        if ($attempts >= $maxAttempts) {
            $result['reason'] = "Maximum repost attempts ({$maxAttempts}) reached.";

            return $result;
        }

        $now = now();

        $minAge = max(0, (int) ($config['min_age_minutes'] ?? 0));
        if ($minAge > 0) {
            $receivedAt = $lead->received_at ?? $lead->created_at;
            if ($receivedAt) {
                $eligibleAt = $receivedAt->copy()->addMinutes($minAge);
                if ($now->lt($eligibleAt)) {
                    $result['reason'] = "Lead must be at least {$minAge} minutes old before repost.";
                    $result['next_eligible_at'] = $eligibleAt->toIso8601String();

                    return $result;
                }
            }
        }

        $cooldown = max(0, (int) ($config['cooldown_minutes'] ?? 0));
        if ($cooldown > 0) {
            $lastReposted = $lead->metadata['last_reposted_at'] ?? null;
            if ($lastReposted) {
                $eligibleAt = Carbon::parse($lastReposted)->addMinutes($cooldown);
                if ($now->lt($eligibleAt)) {
                    $result['reason'] = "Repost cooldown active — wait {$cooldown} minutes between attempts.";
                    $result['next_eligible_at'] = $eligibleAt->toIso8601String();

                    return $result;
                }
            }
        }

        $result['eligible'] = true;

        return $result;
    }

    public function isValidationQuarantine(Lead $lead): bool
    {
        if ($lead->status !== LeadStatus::Quarantined) {
            return false;
        }

        $reason = $lead->metadata['quarantine_reason'] ?? null;

        return $reason === 'validation'
            || ! empty($lead->metadata['email_validation'])
            || ! empty($lead->metadata['hlr_validation'])
            || ! empty($lead->metadata['field_validation']);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function repost(Lead $lead): array
    {
        $eligibility = $this->eligibility($lead);

        if (! $eligibility['eligible']) {
            return [
                'success' => false,
                'message' => $eligibility['reason'] ?? 'Lead is not eligible for repost.',
            ];
        }

        $attempts = $eligibility['attempts'];

        $lead->update([
            'status' => LeadStatus::Accepted,
            'quarantined_until' => null,
            'reject_reason' => null,
            'sold_to_buyer_id' => null,
            'distributed_at' => null,
            'metadata' => array_merge($lead->metadata ?? [], [
                'repost_attempts' => $attempts + 1,
                'last_reposted_at' => now()->toIso8601String(),
            ]),
        ]);

        LeadJobDispatcher::dispatch($lead->id);

        return [
            'success' => true,
            'message' => 'Lead queued for repost through the ping tree.',
        ];
    }
}
