<?php

namespace App\Console\Commands;

use App\Services\Platform\PlatformNotificationService;
use App\Services\Platform\PlatformStatusService;
use Illuminate\Console\Command;

class PlatformSyncAlertsCommand extends Command
{
    protected $signature = 'platform:sync-alerts';

    protected $description = 'Sync operational issues into super-admin system alerts';

    public function handle(PlatformStatusService $status): int
    {
        $status->refresh();

        $active = app(PlatformNotificationService::class)->activeSystemAlerts();

        $this->info('Super-admin alerts synced: '.$active->count().' active');

        foreach ($active as $alert) {
            $severity = strtoupper($alert->severity);
            $this->line("  [{$severity}] {$alert->title}");
        }

        return self::SUCCESS;
    }
}
