<?php

namespace App\Console\Commands;

use App\Services\Automation\AutomationSequenceService;
use Illuminate\Console\Command;

class ProcessAutomationSequencesCommand extends Command
{
    protected $signature = 'automation:process-sequences';

    protected $description = 'Process due automation sequence enrollments (drip journeys)';

    public function handle(AutomationSequenceService $sequences): int
    {
        $count = $sequences->processDueEnrollments();

        $this->info("Processed {$count} automation enrollment(s).");

        return self::SUCCESS;
    }
}
