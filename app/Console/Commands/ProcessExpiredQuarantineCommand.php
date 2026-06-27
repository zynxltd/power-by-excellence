<?php

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Jobs\ProcessLeadJob;
use App\Models\Lead;
use App\Services\Logging\PlatformLogger;
use Illuminate\Console\Command;

class ProcessExpiredQuarantineCommand extends Command
{
    protected $signature = 'quarantine:process-expired';

    protected $description = 'Release or reject quarantined leads whose hold period has expired';

    public function handle(): int
    {
        $leads = Lead::withoutGlobalScopes()
            ->where('status', LeadStatus::Quarantined)
            ->whereNotNull('quarantined_until')
            ->where('quarantined_until', '<=', now())
            ->get();

        $released = 0;
        $rejected = 0;

        foreach ($leads as $lead) {
            if ($this->shouldRejectOnExpiry($lead)) {
                $lead->update([
                    'status' => LeadStatus::Rejected,
                    'reject_reason' => 'Quarantine hold expired - validation not resolved',
                    'quarantined_until' => null,
                ]);
                PlatformLogger::leadEvent($lead, 'lead.quarantine_expired', 'Rejected after hold expired', [
                    'action' => 'reject',
                ]);
                $rejected++;
                continue;
            }

            $lead->update([
                'status' => LeadStatus::Accepted,
                'quarantined_until' => null,
            ]);
            ProcessLeadJob::dispatch($lead->id);
            PlatformLogger::leadEvent($lead, 'lead.quarantine_expired', 'Released after hold expired', [
                'action' => 'release',
            ]);
            $released++;
        }

        $this->info("Processed {$leads->count()} expired quarantine lead(s): {$released} released, {$rejected} rejected.");

        return self::SUCCESS;
    }

    protected function shouldRejectOnExpiry(Lead $lead): bool
    {
        $reason = $lead->metadata['quarantine_reason'] ?? null;
        $meta = $lead->metadata ?? [];

        if ($reason === 'validation'
            || ! empty($meta['email_validation'])
            || ! empty($meta['hlr_validation'])
            || ! empty($meta['field_validation'])) {
            return true;
        }

        return config('validation.quarantine_expire_action', 'release') === 'reject';
    }
}
