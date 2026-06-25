<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessLogController extends Controller
{
    public function index(Request $request): Response
    {
        $days = (int) $request->input('days', 7);
        $days = in_array($days, [1, 7, 14, 28], true) ? $days : 7;
        $since = $request->filled('date_from')
            ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->subDays($days)->startOfDay();
        $until = $request->filled('date_to')
            ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfDay();

        $query = AccessLog::with('user:id,name,email,role')
            ->when(AccountContext::id(), fn ($q, $id) => $q->where('account_id', $id))
            ->whereBetween('created_at', [$since, $until])
            ->orderByDesc('created_at');

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }

        $logs = $query->paginate(30)->withQueryString();

        return Inertia::render('Admin/Logs/AccessLogs', [
            'logs' => $logs,
            'filters' => [
                'days' => $days,
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'action' => $request->input('action'),
                'q' => $request->input('q'),
            ],
            'actionOptions' => ['login', 'logout', 'failed'],
        ]);
    }
}
