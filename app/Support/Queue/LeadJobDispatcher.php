<?php

namespace App\Support\Queue;

use App\Jobs\ProcessLeadJob;

class LeadJobDispatcher
{
    public static function dispatch(int $leadId): void
    {
        try {
            ProcessLeadJob::dispatch($leadId);
        } catch (\Throwable) {
            ProcessLeadJob::dispatchSync($leadId);
        }
    }
}
