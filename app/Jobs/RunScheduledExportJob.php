<?php

namespace App\Jobs;

use App\Models\ScheduledExport;
use App\Services\Exports\ScheduledExportRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunScheduledExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $scheduledExportId) {}

    public function handle(ScheduledExportRunner $runner): void
    {
        $export = ScheduledExport::withoutGlobalScopes()->find($this->scheduledExportId);

        if (! $export || $export->status !== 'active') {
            return;
        }

        $runner->run($export);
    }
}
