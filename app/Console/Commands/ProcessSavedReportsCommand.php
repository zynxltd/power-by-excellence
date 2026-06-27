<?php

namespace App\Console\Commands;

use App\Models\SavedReport;
use App\Services\Exports\SavedReportRunner;
use Illuminate\Console\Command;

class ProcessSavedReportsCommand extends Command
{
    protected $signature = 'reports:process-scheduled';

    protected $description = 'Run saved reports that have a schedule_cron and are due';

    public function handle(SavedReportRunner $runner): int
    {
        $processed = 0;

        SavedReport::query()
            ->where('status', 'active')
            ->whereNotNull('schedule_cron')
            ->whereNotNull('email_recipients')
            ->each(function (SavedReport $report) use ($runner, &$processed) {
                if ($this->isDue($report) && $runner->run($report)) {
                    $processed++;
                }
            });

        $this->info("Processed {$processed} saved report(s).");

        return self::SUCCESS;
    }

    protected function isDue(SavedReport $report): bool
    {
        if (! $report->last_run_at) {
            return true;
        }

        return $report->last_run_at->lt(now()->subHour());
    }
}
