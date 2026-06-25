<?php

namespace App\Console\Commands;

use App\Services\Platform\PlatformStatusService;
use Illuminate\Console\Command;

class PlatformStatusSnapshotCommand extends Command
{
    protected $signature = 'platform:status-snapshot {--persist : Store a daily snapshot for uptime history}';

    protected $description = 'Refresh cached platform health status (scheduled checks for Command Center and public status page)';

    public function handle(PlatformStatusService $status): int
    {
        $persist = (bool) $this->option('persist');
        $payload = $status->refresh($persist);

        $this->info('Platform status: '.$payload['label'].' ('.$payload['status'].')');
        $this->line('  Checked at: '.$payload['checked_at']);

        foreach ($payload['checks'] as $check) {
            $icon = match ($check['status']) {
                'ok' => '<fg=green>OK</>',
                'warning' => '<fg=yellow>WARN</>',
                default => '<fg=red>FAIL</>',
            };
            $this->line("  {$icon}  {$check['label']}: {$check['message']}");
        }

        if ($persist) {
            $this->comment('Daily snapshot stored.');
        }

        return $payload['status'] === 'outage' ? self::FAILURE : self::SUCCESS;
    }
}
