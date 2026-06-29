<?php

namespace App\Services\Exports;

use App\Mail\SavedReportExportMail;
use App\Models\Lead;
use App\Models\SavedReport;
use App\Support\Tenancy\AccountContext;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SavedReportRunner
{
    public function __construct(protected LeadExportService $leadExport) {}

    public function run(SavedReport $report, bool $scheduled = false): bool
    {
        $report->loadMissing('account');
        AccountContext::set($report->account);

        try {
            $recipients = SavedReportSchedule::normalizeRecipients($report->email_recipients ?? []);

            if ($recipients === []) {
                SavedReportSchedule::syncAfterRun($report, false);

                return false;
            }

            $query = Lead::query()->where('account_id', $report->account_id);
            $query = $this->leadExport->applyFilters($query, $report->filters ?? []);

            $csv = $this->leadExport->buildCsvFromQuery($query);
            $filename = 'report-'.now()->format('Y-m-d-His').'.csv';

            Mail::to($recipients)->send(new SavedReportExportMail($report, $csv, $filename));

            SavedReportSchedule::syncAfterRun($report, true);

            return true;
        } catch (Throwable $e) {
            report($e);
            SavedReportSchedule::syncAfterRun($report, false);

            return false;
        } finally {
            AccountContext::clear();
        }
    }
}
