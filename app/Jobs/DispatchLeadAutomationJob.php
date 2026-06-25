<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\Automation\AutomationSequenceService;
use App\Services\Automation\AutoResponderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchLeadAutomationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $leadId,
        public string $triggerEvent = 'on_lead_received',
    ) {}

    public function handle(AutoResponderService $autoResponders, AutomationSequenceService $sequences): void
    {
        $lead = Lead::withoutGlobalScopes()->find($this->leadId);

        if (! $lead) {
            return;
        }

        $autoResponders->dispatchForLead($lead, $this->triggerEvent);
        $sequences->dispatchForLead($lead, $this->triggerEvent);
    }
}
