<?php

namespace App\Console\Commands;

use App\Services\Platform\PlatformOpsCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class LinkTenantsCommand extends Command
{
    protected $signature = 'platform:link-tenants {--dry-run : Print commands without executing}';

    protected $description = 'Link all tenant subdomains in Laravel Herd (local dev only)';

    public function handle(PlatformOpsCheck $checks): int
    {
        $status = $checks->herdLinkStatus();

        if (! $status['needs_linking'] && empty($status['missing'])) {
            $this->info('All tenant subdomains already resolve.');

            return self::SUCCESS;
        }

        $targets = $status['missing'] ?: array_map(
            fn (string $cmd) => str_replace('herd link ', '', $cmd),
            $status['commands']
        );

        if ($this->option('dry-run')) {
            foreach ($targets as $host) {
                $this->line("herd link {$host}");
            }

            return self::SUCCESS;
        }

        if (! $this->herdAvailable()) {
            $this->warn('Herd CLI not found. Run these commands manually:');
            foreach ($targets as $host) {
                $this->line("  herd link {$host}");
            }

            return self::FAILURE;
        }

        foreach ($targets as $host) {
            $this->line("Linking {$host}…");
            $result = Process::run(['herd', 'link', $host]);

            if ($result->successful()) {
                $this->info("  ✓ {$host}");
            } else {
                $this->error("  ✗ {$host}: ".$result->errorOutput());
            }
        }

        return self::SUCCESS;
    }

    protected function herdAvailable(): bool
    {
        $result = Process::run(['which', 'herd']);

        return $result->successful() && trim($result->output()) !== '';
    }
}
