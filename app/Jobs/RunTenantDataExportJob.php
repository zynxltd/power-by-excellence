<?php

namespace App\Jobs;

use App\Models\TenantDataExport;
use App\Services\Compliance\TenantDataExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunTenantDataExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $tenantDataExportId) {}

    public function handle(TenantDataExportService $service): void
    {
        $export = TenantDataExport::withoutGlobalScopes()->find($this->tenantDataExportId);

        if (! $export || ! in_array($export->status, ['pending', 'processing'], true)) {
            return;
        }

        $service->run($export);
    }
}
