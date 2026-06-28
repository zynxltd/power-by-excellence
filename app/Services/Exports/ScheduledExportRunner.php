<?php

namespace App\Services\Exports;

use App\Models\Lead;
use App\Models\ScheduledExport;
use App\Support\Tenancy\AccountContext;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ScheduledExportRunner
{
    public function __construct(
        protected LeadExportService $leadExport,
        protected ScheduledExportRemoteDelivery $remoteDelivery,
    ) {}

    public function run(ScheduledExport $export): bool
    {
        AccountContext::set($export->account);

        try {
            $query = $this->buildQuery($export);
            $csv = $this->leadExport->buildCsvFromQuery($query);
            $filename = 'export-'.now()->format('Y-m-d-His').'.csv';

            match ($export->delivery_method) {
                'ftp', 'sftp' => $this->remoteDelivery->upload($export, $csv, $filename),
                default => $this->emailCsv($export, $csv, $filename),
            };

            $export->update(['last_run_at' => now()]);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        } finally {
            AccountContext::clear();
        }
    }

    protected function buildQuery(ScheduledExport $export)
    {
        $config = $export->config ?? [];
        $filters = $config['filters'] ?? [];

        $query = Lead::query()->where('account_id', $export->account_id);

        if ($export->buyer_id) {
            $query->where('sold_to_buyer_id', $export->buyer_id);
        }

        return $this->leadExport->applyFilters($query, $filters);
    }

    protected function emailCsv(ScheduledExport $export, string $csv, string $filename): void
    {
        $config = $export->config ?? [];
        $recipients = $config['email_recipients'] ?? $config['recipients'] ?? [];

        if (empty($recipients)) {
            throw new \RuntimeException('No email recipients configured for scheduled export.');
        }

        Mail::raw('Scheduled export attached.', function ($message) use ($recipients, $filename, $csv, $export) {
            $message->to($recipients);
            $message->subject("Scheduled export: {$export->name}");
            $message->attachData($csv, $filename, ['mime' => 'text/csv']);
        });
    }
}
