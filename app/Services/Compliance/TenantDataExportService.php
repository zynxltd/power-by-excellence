<?php

namespace App\Services\Compliance;

use App\Models\AccessLog;
use App\Models\Account;
use App\Models\AccountAuditLog;
use App\Models\Lead;
use App\Models\MarketingOptOut;
use App\Models\TenantDataExport;
use App\Models\User;
use App\Services\Exports\LeadExportService;
use App\Support\CsvExport;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class TenantDataExportService
{
    public const QUEUE_LEAD_THRESHOLD = 500;

    public function __construct(
        protected LeadExportService $leadExporter,
    ) {}

    public function leadCount(Account $account): int
    {
        return Lead::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->count();
    }

    public function shouldQueue(Account $account): bool
    {
        return $this->leadCount($account) > self::QUEUE_LEAD_THRESHOLD;
    }

    public function request(Account $account, ?int $requestedBy): TenantDataExport
    {
        return TenantDataExport::create([
            'account_id' => $account->id,
            'requested_by' => $requestedBy,
            'status' => 'pending',
            'lead_count' => $this->leadCount($account),
        ]);
    }

    public function run(TenantDataExport $export): void
    {
        $export->update(['status' => 'processing']);

        try {
            $account = Account::findOrFail($export->account_id);
            $path = $this->buildZip($account, $export);

            $export->update([
                'status' => 'ready',
                'storage_path' => $path,
                'completed_at' => now(),
                'expires_at' => now()->addDays(7),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $export->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function buildZip(Account $account, TenantDataExport $export): string
    {
        $disk = Storage::disk('local');
        $directory = "tenant-exports/{$account->id}";
        $disk->makeDirectory($directory);

        $zipRelativePath = "{$directory}/sar-export-{$export->id}.zip";
        $zipAbsolutePath = $disk->path($zipRelativePath);

        if (file_exists($zipAbsolutePath)) {
            unlink($zipAbsolutePath);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipAbsolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create export archive.');
        }

        $zip->addFromString('leads.csv', $this->buildLeadsCsv($account));
        $zip->addFromString('users.csv', $this->buildUsersCsv($account));
        $zip->addFromString('access_logs.csv', $this->buildAccessLogsCsv($account));
        $zip->addFromString('audit_logs.csv', $this->buildAuditLogsCsv($account));
        $zip->addFromString('marketing_opt_outs.csv', $this->buildMarketingOptOutsCsv($account));
        $zip->addFromString('manifest.json', json_encode([
            'account_id' => $account->id,
            'account_name' => $account->name,
            'export_id' => $export->id,
            'generated_at' => now()->toIso8601String(),
            'lead_count' => $export->lead_count,
            'files' => [
                'leads.csv',
                'users.csv',
                'access_logs.csv',
                'audit_logs.csv',
                'marketing_opt_outs.csv',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $zip->close();

        return $zipRelativePath;
    }

    protected function buildLeadsCsv(Account $account): string
    {
        $query = Lead::withoutGlobalScopes()->where('account_id', $account->id);

        return $this->leadExporter->buildCsvFromQuery($query, 50000);
    }

    protected function buildUsersCsv(Account $account): string
    {
        $users = User::query()
            ->where('account_id', $account->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'role', 'buyer_id', 'supplier_id', 'two_factor_enabled', 'is_suspended', 'created_at']);

        $csv = "id,name,email,phone,role,buyer_id,supplier_id,two_factor_enabled,is_suspended,created_at\n";

        foreach ($users as $user) {
            $csv .= CsvExport::escapeRow([
                $user->id,
                $user->name,
                $user->email,
                $user->phone,
                $user->role?->value ?? $user->role,
                $user->buyer_id,
                $user->supplier_id,
                $user->two_factor_enabled ? 'yes' : 'no',
                $user->is_suspended ? 'yes' : 'no',
                $user->created_at,
            ])."\n";
        }

        return $csv;
    }

    protected function buildAccessLogsCsv(Account $account): string
    {
        $logs = AccessLog::query()
            ->with('user:id,name,email')
            ->where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->limit(10000)
            ->get();

        $csv = "id,user_name,user_email,action,ip_address,path,user_agent,created_at\n";

        foreach ($logs as $log) {
            $csv .= CsvExport::escapeRow([
                $log->id,
                $log->user?->name,
                $log->user?->email,
                $log->action,
                $log->ip_address,
                $log->path,
                $log->user_agent,
                $log->created_at,
            ])."\n";
        }

        return $csv;
    }

    protected function buildAuditLogsCsv(Account $account): string
    {
        $logs = AccountAuditLog::query()
            ->with('user:id,name,email')
            ->where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->limit(10000)
            ->get();

        $csv = "id,user_name,user_email,action,entity_type,entity_id,changes,ip_address,created_at\n";

        foreach ($logs as $log) {
            $csv .= CsvExport::escapeRow([
                $log->id,
                $log->user?->name,
                $log->user?->email,
                $log->action,
                $log->entity_type,
                $log->entity_id,
                json_encode($log->changes),
                $log->ip_address,
                $log->created_at,
            ])."\n";
        }

        return $csv;
    }

    protected function buildMarketingOptOutsCsv(Account $account): string
    {
        $rows = MarketingOptOut::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->get();

        $csv = "id,field_type,label,hash,source,created_at\n";

        foreach ($rows as $row) {
            $csv .= CsvExport::escapeRow([
                $row->id,
                $row->field_type,
                $row->label,
                $row->hash,
                $row->source,
                $row->created_at,
            ])."\n";
        }

        return $csv;
    }
}
