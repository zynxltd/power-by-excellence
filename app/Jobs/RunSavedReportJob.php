<?php

namespace App\Jobs;

use App\Models\SavedReport;
use App\Services\Exports\SavedReportRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunSavedReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $savedReportId) {}

    public function handle(SavedReportRunner $runner): void
    {
        $report = SavedReport::withoutGlobalScopes()->find($this->savedReportId);

        if (! $report || $report->status !== 'active') {
            return;
        }

        $runner->run($report, scheduled: true);
    }
}
