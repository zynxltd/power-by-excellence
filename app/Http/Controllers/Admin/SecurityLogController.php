<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountAuditLog;
use App\Models\AccessLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecurityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $days = (int) $request->input('days', 7);
        $accountId = \App\Support\Tenancy\AccountContext::id();

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
        $deliveryQuery = \App\Models\DeliveryLog::query()
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
            'filters' => $request->only(['action']),
        ]);
    }
}
