<?php

namespace App\Console\Commands;

use App\Jobs\RunScheduledExportJob;
use App\Models\ScheduledExport;
use Cron\CronExpression;
use Illuminate\Console\Command;

class ProcessScheduledExportsCommand extends Command
{
    protected $signature = 'exports:process';

    protected $description = 'Dispatch scheduled CSV exports that are due to run';

    public function handle(): int
    {
        $exports = ScheduledExport::withoutGlobalScopes()
            ->where('status', 'active')
            ->get();

        $dispatched = 0;

        foreach ($exports as $export) {
            if (! $this->isDue($export)) {
                continue;
            }

            RunScheduledExportJob::dispatch($export->id);
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} scheduled export(s).");

        return self::SUCCESS;
    }

    protected function isDue(ScheduledExport $export): bool
    {
        $cron = $export->cron ?: '0 8 * * *';

        return CronExpression::factory($cron)->isDue(now());
    }
}
