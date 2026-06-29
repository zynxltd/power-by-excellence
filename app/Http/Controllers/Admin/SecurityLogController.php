<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesLogDateRange;
use App\Http\Controllers\Controller;
use App\Models\AccountAuditLog;
use App\Models\AccessLog;
use App\Models\DeliveryLog;
use App\Support\CsvExport;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SecurityLogController extends Controller
{
    use ResolvesLogDateRange;

    public function index(Request $request): InertiaResponse
    {
        $days = (int) $request->input('days', 7);
        $accountId = AccountContext::id();

        $accessQuery = AccessLog::with('user:id,name,email')
            ->orderByDesc('created_at')
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action));

        $accessLogs = (clone $accessQuery)
            ->paginate(25, ['*'], 'access_page')
            ->withQueryString();

        $auditQuery = AccountAuditLog::with('user:id,name,email')
            ->orderByDesc('created_at')
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId));

        $auditLogs = (clone $auditQuery)
            ->paginate(25, ['*'], 'audit_page')
            ->withQueryString();

        $accessSince = now()->subDay();
        $deliveryQuery = DeliveryLog::query()
            ->when($accountId, fn ($q) => $q->forCurrentAccount())
            ->where('created_at', '>=', $accessSince);

        $stats = [
            'logins_today' => AccessLog::query()
                ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
                ->where('action', 'login')
                ->whereDate('created_at', today())
                ->count(),
            'failed_logins_24h' => 0,
            'unique_ips_24h' => AccessLog::query()
                ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
                ->where('created_at', '>=', $accessSince)
                ->distinct('ip_address')
                ->count('ip_address'),
            'audit_events_24h' => (clone $auditQuery)->where('created_at', '>=', $accessSince)->count(),
            'delivery_errors_24h' => (clone $deliveryQuery)->where('status', 'failed')->count(),
            'avg_delivery_ms' => round((float) (clone $deliveryQuery)->avg('duration_ms'), 0),
        ];

        return Inertia::render('Admin/Logs/Security', [
            'accessLogs' => $accessLogs,
            'auditLogs' => $auditLogs,
            'stats' => $stats,
            'days' => $days,
            'filters' => $request->only(['action', 'days', 'date_from', 'date_to']),
        ]);
    }

    public function export(Request $request): Response
    {
        [$since, $until] = $this->logDateRange($request);
        $accountId = AccountContext::id() ?? $request->user()?->account_id;

        $accessRows = AccessLog::with('user:id,name,email')
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->whereBetween('created_at', [$since, $until])
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get()
            ->map(fn (AccessLog $log) => [
                'access',
                $log->created_at?->toDateTimeString(),
                $log->user?->name ?? '',
                $log->user?->email ?? '',
                $log->action,
                '',
                '',
                $log->ip_address ?? '',
                $log->path ?? '',
            ]);

        $auditRows = AccountAuditLog::with('user:id,name,email')
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->whereBetween('created_at', [$since, $until])
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get()
            ->map(fn (AccountAuditLog $log) => [
                'audit',
                $log->created_at?->toDateTimeString(),
                $log->user?->name ?? 'System',
                $log->user?->email ?? '',
                $log->action,
                $log->entity_type ?? '',
                $log->entity_id ?? '',
                $log->ip_address ?? '',
                '',
            ]);

        $rows = $accessRows->concat($auditRows)->sortByDesc(fn (array $row) => $row[1])->values();

        $csv = CsvExport::escapeRow([
            'record_type', 'created_at', 'user_name', 'user_email', 'action', 'entity_type', 'entity_id', 'ip_address', 'path',
        ])."\n";

        foreach ($rows as $row) {
            $csv .= CsvExport::escapeRow($row)."\n";
        }

        return CsvExport::download($csv, 'security-logs-'.now()->format('Y-m-d-His').'.csv');
    }
}
