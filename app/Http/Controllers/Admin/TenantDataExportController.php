<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunTenantDataExportJob;
use App\Models\TenantDataExport;
use App\Services\Compliance\TenantDataExportService;
use App\Services\Security\AuditLogService;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantDataExportController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request, TenantDataExportService $exports): Response
    {
        $account = $this->resolveAdminAccount($request);

        return Inertia::render('Admin/Tools/DataExport', [
            'exports' => TenantDataExport::query()
                ->with('requester:id,name,email')
                ->orderByDesc('created_at')
                ->paginate(15),
            'leadCount' => $exports->leadCount($account),
            'queueThreshold' => TenantDataExportService::QUEUE_LEAD_THRESHOLD,
        ]);
    }

    public function store(Request $request, TenantDataExportService $exports): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);

        $export = $exports->request($account, $request->user()?->id);

        app(AuditLogService::class)->record(
            'tenant_data_export.requested',
            'tenant_data_export',
            $export->id,
            ['lead_count' => $export->lead_count],
        );

        if ($exports->shouldQueue($account)) {
            RunTenantDataExportJob::dispatch($export->id);

            return back()->with('success', 'Data export queued. Refresh this page when processing completes.');
        }

        try {
            $exports->run($export);
        } catch (\Throwable) {
            return back()->withErrors(['export' => 'Export failed. Check logs and try again.']);
        }

        return back()->with('success', 'Data export is ready to download.');
    }

    public function download(Request $request, TenantDataExport $tenantDataExport): StreamedResponse|RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);
        abort_unless($tenantDataExport->account_id === $account->id, 404);
        abort_unless($tenantDataExport->isReady(), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($tenantDataExport->storage_path), 404);

        app(AuditLogService::class)->record(
            'tenant_data_export.downloaded',
            'tenant_data_export',
            $tenantDataExport->id,
        );

        return $disk->download(
            $tenantDataExport->storage_path,
            "tenant-data-export-{$account->slug}-{$tenantDataExport->id}.zip",
        );
    }
}
