<?php

namespace App\Jobs;

use App\Services\Alerts\EventAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateEventAlertsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $accountId) {}

    public function handle(EventAlertService $service): void
    {
        $service->evaluateForAccount($this->accountId);
    }
}
