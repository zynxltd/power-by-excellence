<?php

namespace App\Console\Commands;

use App\Jobs\RunSavedReportJob;
use App\Models\SavedReport;
use App\Services\Exports\SavedReportSchedule;
use Illuminate\Console\Command;

class ProcessSavedReportsCommand extends Command
{
    protected $signature = 'reports:process-scheduled';

    protected $description = 'Dispatch saved report export jobs that are due to run';

    public function handle(): int
    {
        $dispatched = 0;

        SavedReport::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNotNull('schedule_cron')
            ->whereNotNull('email_recipients')
            ->orderBy('id')
            ->each(function (SavedReport $report) use (&$dispatched) {
                if (! SavedReportSchedule::isDue($report)) {
                    return;
                }

                RunSavedReportJob::dispatch($report->id);
                $dispatched++;
            });

        $this->info("Dispatched {$dispatched} saved report job(s).");

        return self::SUCCESS;
    }
}
