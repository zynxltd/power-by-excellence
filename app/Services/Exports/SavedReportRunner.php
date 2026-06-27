<?php

namespace App\Services\Exports;

use App\Models\Lead;
use App\Models\SavedReport;
use App\Support\Tenancy\AccountContext;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SavedReportRunner
{
    public function __construct(protected LeadExportService $leadExport) {}

    public function run(SavedReport $report): bool
    {
        AccountContext::set($report->account);

        try {
            $query = Lead::query()->where('account_id', $report->account_id);
            $query = $this->leadExport->applyFilters($query, $report->filters ?? []);

            $csv = $this->leadExport->buildCsvFromQuery($query);
            $filename = 'report-'.now()->format('Y-m-d-His').'.csv';

            $recipients = $report->email_recipients ?? [];
            if ($recipients !== []) {
                Mail::raw("Saved report \"{$report->name}\" attached.", function ($message) use ($recipients, $filename, $csv, $report) {
                    $message->to($recipients);
                    $message->subject("Report: {$report->name}");
                    $message->attachData($csv, $filename, ['mime' => 'text/csv']);
                });
            }

            $report->update(['last_run_at' => now()]);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        } finally {
            AccountContext::clear();
        }
    }
}
