<?php

namespace App\Services\Exports;

use App\Models\Lead;
use App\Models\ScheduledExport;
use App\Support\CsvExport;
use App\Support\Tenancy\AccountContext;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ScheduledExportRunner
{
    public function __construct(protected LeadExportService $leadExport) {}

    public function run(ScheduledExport $export): bool
    {
        AccountContext::set($export->account);

        try {
            $query = $this->buildQuery($export);
            $csv = $this->leadExport->buildCsvFromQuery($query);
            $filename = 'export-'.now()->format('Y-m-d-His').'.csv';

            if ($export->delivery_method === 'ftp') {
                $this->uploadFtp($export, $csv, $filename);
            } else {
                $this->emailCsv($export, $csv, $filename);
            }

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

    protected function uploadFtp(ScheduledExport $export, string $csv, string $filename): void
    {
        $config = $export->config ?? [];
        $host = $config['host'] ?? null;
        $user = $config['user'] ?? null;
        $pass = $config['pass'] ?? null;
        $path = rtrim($config['path'] ?? '/', '/').'/'.$filename;

        if (! $host || ! $user) {
            throw new \RuntimeException('FTP host and user are required for FTP delivery.');
        }

        $tempPath = 'scheduled-exports/'.$export->id.'/'.$filename;
        Storage::disk('local')->put($tempPath, $csv);

        $connection = ftp_connect($host);
        if (! $connection) {
            throw new \RuntimeException("Unable to connect to FTP host: {$host}");
        }

        try {
            if (! @ftp_login($connection, $user, $pass ?? '')) {
                throw new \RuntimeException('FTP login failed.');
            }

            ftp_pasv($connection, true);

            $localPath = Storage::disk('local')->path($tempPath);
            if (! ftp_put($connection, $path, $localPath, FTP_BINARY)) {
                throw new \RuntimeException("FTP upload failed for {$path}");
            }
        } finally {
            ftp_close($connection);
            Storage::disk('local')->delete($tempPath);
        }
    }
}
