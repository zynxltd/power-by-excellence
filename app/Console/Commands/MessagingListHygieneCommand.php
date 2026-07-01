<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\Messaging\ListHygieneService;
use Illuminate\Console\Command;

class MessagingListHygieneCommand extends Command
{
    protected $signature = 'messaging:list-hygiene {--account= : Limit to a single account ID} {--dry-run : Preview changes without tagging leads}';

    protected $description = 'Scrub bounced leads and tag inactive marketing contacts';

    public function handle(ListHygieneService $hygiene): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $accountId = $this->option('account');

        if ($accountId) {
            $account = Account::findOrFail($accountId);
            $result = $hygiene->run($account, $dryRun, force: true);
            $this->reportAccount($account->name, $result);

            return self::SUCCESS;
        }

        $processed = $hygiene->runForEnabledAccounts($dryRun);
        $this->info(($dryRun ? 'Dry-run: ' : '')."Processed {$processed} account(s) with list hygiene enabled.");

        return self::SUCCESS;
    }

    /**
     * @param  array{bounces_tagged: int, inactive_tagged: int, dry_run: bool, skipped: bool}  $result
     */
    protected function reportAccount(string $name, array $result): void
    {
        if ($result['skipped']) {
            $this->warn("{$name}: list hygiene disabled — skipped.");

            return;
        }

        $prefix = $result['dry_run'] ? '[dry-run] ' : '';
        $this->info("{$prefix}{$name}: {$result['bounces_tagged']} bounce(s), {$result['inactive_tagged']} inactive lead(s).");
    }
}
