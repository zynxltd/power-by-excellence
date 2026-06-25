<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\Leads\LeadPipeline;
use App\Services\Logging\PlatformLogger;
use App\Support\Tenancy\AccountContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessLeadJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 5;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(public int $leadId) {}

    public function uniqueId(): string
    {
        return 'process-lead-'.$this->leadId;
    }

    public function handle(LeadPipeline $pipeline): void
    {
        $lead = Lead::withoutGlobalScopes()->with('campaign.account')->findOrFail($this->leadId);

        AccountContext::set($lead->campaign->account);

        $pipeline->process($lead);
    }

    public function failed(Throwable $exception): void
    {
        $lead = Lead::withoutGlobalScopes()->find($this->leadId);
        if ($lead) {
            $lead->update(['status' => 'rejected', 'reject_reason' => 'Queue processing failed']);
            PlatformLogger::error('ProcessLeadJob failed permanently', [
                'lead_id' => $this->leadId,
            ], $lead, $exception);
        }
    }
}
