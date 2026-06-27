<?php

namespace App\Console\Commands;

use App\Models\Buyer;
use App\Services\Demo\BuyerPortalHistorySeeder;
use Illuminate\Console\Command;

class SeedBuyerPortalHistoryCommand extends Command
{
    protected $signature = 'demo:seed-buyer-portal
                            {email : Buyer or portal user email}
                            {--days=90 : Days of historical leads}
                            {--replace : Remove existing sold leads and ledger for this buyer first}';

    protected $description = 'Seed sold leads, feedback, returns, and ledger history for a buyer portal dashboard';

    public function handle(BuyerPortalHistorySeeder $seeder): int
    {
        $email = (string) $this->argument('email');
        $days = max(1, (int) $this->option('days'));

        $user = \App\Models\User::query()->where('email', $email)->whereNotNull('buyer_id')->first();
        $buyer = $user?->buyer ?? Buyer::query()->where('email', $email)->first();

        if (! $buyer) {
            $this->error("No buyer found for {$email}");

            return self::FAILURE;
        }

        if ($this->option('replace')) {
            $this->warn("Clearing existing portal data for {$buyer->name}…");
        }

        $result = $seeder->seed($buyer, $days, (bool) $this->option('replace'));

        $this->info("Seeded {$buyer->name} ({$buyer->email}) on {$buyer->account?->slug}:");
        $this->line("  Leads sold: {$result['leads']}");
        $this->line("  Feedback: {$result['feedback']}");
        $this->line("  Returns: {$result['returns']}");
        $this->line("  Ledger entries: {$result['transactions']}");
        $this->line('  Credit balance: '.number_format((float) $buyer->fresh()->credit_balance, 2));

        return self::SUCCESS;
    }
}
