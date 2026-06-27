<?php

namespace App\Services\Calls;

use App\Models\CallSession;
use App\Services\Postbacks\PostbackDispatcher;

class CallPostbackService
{
    public function __construct(
        protected PostbackDispatcher $dispatcher,
    ) {}

    public function dispatchSold(CallSession $session): void
    {
        $lead = $session->lead;

        if (! $lead) {
            return;
        }

        $this->dispatcher->dispatch($lead, 'call_sold');
    }
}
