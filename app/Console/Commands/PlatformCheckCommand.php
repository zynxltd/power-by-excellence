<?php

namespace App\Console\Commands;

use App\Services\Platform\PlatformOpsCheck;
use Illuminate\Console\Command;

class PlatformCheckCommand extends Command
{
    protected $signature = 'platform:check';

    protected $description = 'Run platform operational health checks (tenancy, session, queue, database)';

    public function handle(PlatformOpsCheck $checks): int
    {
        $this->info('PowerByExcellence platform checks');
        $this->newLine();

        $hasCritical = false;

        foreach ($checks->run() as $check) {
            $icon = match ($check['status']) {
                'ok' => '<fg=green>OK</>',
                'warning' => '<fg=yellow>WARN</>',
                default => '<fg=red>FAIL</>',
            };

            $this->line("  {$icon}  {$check['label']}: {$check['message']}");

            if ($check['hint']) {
                $this->line("       → {$check['hint']}");
            }

            if (! empty($check['command'])) {
                $this->line("       $ {$check['command']}");
            }

            if ($check['status'] === 'critical') {
                $hasCritical = true;
            }
        }

        $this->newLine();
        $this->comment('Tenant setup: super admin manages platforms at /accounts. Tenant users sign in on their subdomain only.');

        return $hasCritical ? self::FAILURE : self::SUCCESS;
    }
}
