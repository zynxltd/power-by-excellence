<?php

namespace App\Jobs;

use App\Models\AutomationSequenceStep;
use App\Models\Lead;
use App\Services\Automation\AutomationSequenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAutomationStepJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $leadId,
        public int $stepId,
    ) {}

    public function handle(AutomationSequenceService $service): void
    {
        $lead = Lead::withoutGlobalScopes()->find($this->leadId);
        $step = AutomationSequenceStep::find($this->stepId);

        if ($lead && $step) {
            $service->runStep($lead, $step);
        }
    }
}
