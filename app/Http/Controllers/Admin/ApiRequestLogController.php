<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiRequestLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiRequestLogController extends Controller
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

        $query = ApiRequestLog::with('apiKey:id,name,key_prefix')
            ->whereBetween('created_at', [$since, $until])
            ->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            if ($status === 'error') {
                $query->where('status_code', '>=', 400);
            } elseif ($status === 'success') {
                $query->where('status_code', '<', 400);
            }
        }

        if ($path = $request->input('path')) {
            $query->where('path', 'like', '%'.$path.'%');
        }

        $logs = $query->paginate(50)->withQueryString();

        $base = ApiRequestLog::whereBetween('created_at', [$since, $until]);
        $durations = (clone $base)->pluck('duration_ms')->sort()->values();
        $p95 = $durations->count() > 0
            ? $durations->get((int) floor($durations->count() * 0.95)) ?? $durations->last()
            : 0;

        return Inertia::render('Admin/Logs/Api', [
            'logs' => $logs,
            'filters' => [
                'days' => $days,
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'status' => $request->input('status'),
                'path' => $request->input('path'),
            ],
            'statusOptions' => [
                ['value' => 'success', 'label' => 'Success (<400)'],
                ['value' => 'error', 'label' => 'Errors (≥400)'],
            ],
            'stats' => [
                'total' => (clone $base)->count(),
                'errors' => (clone $base)->where('status_code', '>=', 400)->count(),
                'avg_ms' => (int) round((clone $base)->avg('duration_ms') ?? 0),
                'p95_ms' => (int) $p95,
                'slowest_ms' => (int) ((clone $base)->max('duration_ms') ?? 0),
            ],
        ]);
    }
}
