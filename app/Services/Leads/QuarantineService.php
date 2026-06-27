<?php

namespace App\Services\Leads;

use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Logging\PlatformLogger;
use Carbon\Carbon;

class QuarantineService
{
    public function applyRules(Lead $lead, Campaign $campaign): bool
    {
        if ($this->isOutOfHours($campaign)) {
            $this->quarantine($lead, 'Out of hours - held for next delivery window', $campaign, 'out_of_hours');

            return true;
        }

        return false;
    }

    public function quarantineUnsold(Lead $lead, Campaign $campaign, string $reason = 'Unsold - held for retry'): void
    {
        $config = $campaign->validation_config ?? [];
        if (! ($config['quarantine_unsold'] ?? true)) {
            return;
        }

        $this->quarantine($lead, $reason, $campaign, 'unsold');
    }

    public function quarantineForValidation(Lead $lead, Campaign $campaign, string $reason): void
    {
        $this->quarantine($lead, $reason, $campaign, 'validation');
    }

    protected function quarantine(Lead $lead, string $reason, Campaign $campaign, ?string $reasonCode = null): void
    {
        $hours = (int) ($campaign->validation_config['quarantine_hours'] ?? config('validation.quarantine_hours', 48));
        $until = now()->addHours($hours);

        if (! $reasonCode) {
            $reasonCode = str_contains(strtolower($reason), 'out of hours') ? 'out_of_hours'
                : (str_contains(strtolower($reason), 'unsold') ? 'unsold' : 'hold');
        }

        $lead->update([
            'status' => LeadStatus::Quarantined,
            'quarantined_until' => $until,
            'reject_reason' => null,
            'metadata' => array_merge($lead->metadata ?? [], [
                'quarantine_reason' => $reasonCode,
                'quarantine_message' => $reason,
                'quarantined_at' => now()->toIso8601String(),
            ]),
        ]);

        PlatformLogger::leadEvent($lead, 'lead.quarantined', $reason, [
            'quarantine_reason' => $reasonCode,
            'quarantined_until' => $until->toIso8601String(),
        ]);
    }

    protected function isOutOfHours(Campaign $campaign): bool
    {
        $config = $campaign->validation_config ?? [];
        if (! ($config['quarantine_out_of_hours'] ?? false)) {
            return false;
        }

        $tz = $campaign->account?->timezone ?? 'UTC';
        $now = Carbon::now($tz);
        $start = (int) ($config['delivery_window_start'] ?? 8);
        $end = (int) ($config['delivery_window_end'] ?? 20);

        $hour = (int) $now->format('G');

        return $hour < $start || $hour >= $end;
    }
}
