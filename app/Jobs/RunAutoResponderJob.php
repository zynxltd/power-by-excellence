<?php

namespace App\Jobs;

use App\Models\AutoResponder;
use App\Models\Lead;
use App\Services\Automation\AutoResponderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAutoResponderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $leadId,
        public int $responderId,
    ) {}

    public function handle(AutoResponderService $service): void
    {
        $lead = Lead::withoutGlobalScopes()->find($this->leadId);
        $responder = AutoResponder::find($this->responderId);

        if ($lead && $responder && $responder->status === 'active') {
            $service->sendForLead($responder, $lead);
        }
    }
}
