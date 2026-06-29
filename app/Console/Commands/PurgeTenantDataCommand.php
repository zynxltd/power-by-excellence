<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\Compliance\DataRetentionService;
use Illuminate\Console\Command;

class PurgeTenantDataCommand extends Command
{
    protected $signature = 'data-retention:purge {--account= : Limit purge to a single account id}';

    protected $description = 'Anonymize expired leads and trim old logs per tenant data retention policies';

    public function handle(DataRetentionService $retention): int
    {
        $accounts = Account::query()
            ->where('is_active', true)
            ->when($this->option('account'), fn ($q, $id) => $q->where('id', $id))
            ->orderBy('id')
            ->get();

        if ($accounts->isEmpty()) {
            $this->warn('No active accounts matched the purge criteria.');

            return self::SUCCESS;
        }

        $totals = [
            'leads_anonymized' => 0,
            'logs_deleted' => 0,
            'message_events_deleted' => 0,
        ];

        foreach ($accounts as $account) {
            $result = $retention->purgeAccount($account);
            $totals['leads_anonymized'] += $result['leads_anonymized'];
            $totals['logs_deleted'] += $result['logs_deleted'];
            $totals['message_events_deleted'] += $result['message_events_deleted'];

            if (array_sum($result) > 0) {
                $this->line(sprintf(
                    'Account %s (#%d): %d lead(s) anonymized, %d log row(s) deleted, %d message event(s) deleted.',
                    $account->slug,
                    $account->id,
                    $result['leads_anonymized'],
                    $result['logs_deleted'],
                    $result['message_events_deleted'],
                ));
            }
        }

        $this->info(sprintf(
            'Purge complete: %d lead(s) anonymized, %d log row(s) deleted, %d message event(s) deleted across %d account(s).',
            $totals['leads_anonymized'],
            $totals['logs_deleted'],
            $totals['message_events_deleted'],
            $accounts->count(),
        ));

        return self::SUCCESS;
    }
}
