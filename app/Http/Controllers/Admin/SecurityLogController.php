<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountAuditLog;
use App\Models\AccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SecurityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $days = (int) $request->input('days', 7);

        $accessLogs = AccessLog::with('user:id,name,email')
            ->orderByDesc('created_at')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->paginate(25, ['*'], 'access_page')
            ->withQueryString();

        $auditLogs = AccountAuditLog::with('user:id,name,email')
            ->orderByDesc('created_at')
            ->paginate(25, ['*'], 'audit_page')
            ->withQueryString();

        $stats = [
            'logins_today' => AccessLog::where('action', 'login')->whereDate('created_at', today())->count(),
            'failed_logins_24h' => 0,
            'unique_ips_24h' => AccessLog::where('created_at', '>=', now()->subDay())->distinct('ip_address')->count('ip_address'),
            'audit_events_24h' => AccountAuditLog::where('created_at', '>=', now()->subDay())->count(),
            'delivery_errors_24h' => DB::table('delivery_logs')->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
            'avg_delivery_ms' => round((float) DB::table('delivery_logs')->where('created_at', '>=', now()->subDay())->avg('duration_ms'), 0),
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
