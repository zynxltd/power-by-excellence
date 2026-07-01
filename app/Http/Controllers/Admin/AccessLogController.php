<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesLogDateRange;
use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Support\CsvExport;
use App\Support\Tenancy\AccountContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AccessLogController extends Controller
{
    use ResolvesLogDateRange;

    public function index(Request $request): InertiaResponse
    {
        [$since, $until, $days] = $this->logDateRange($request);

        $logs = $this->filteredQuery($request, $since, $until)
            ->paginate(30)
            ->withQueryString();

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

    public function export(Request $request): Response
    {
        [$since, $until] = $this->logDateRange($request);

        $rows = $this->filteredQuery($request, $since, $until)
            ->limit(10000)
            ->get()
            ->map(fn (AccessLog $log) => [
                $log->created_at?->toDateTimeString(),
                $log->user?->name ?? '',
                $log->user?->email ?? '',
                $log->action,
                $log->ip_address ?? '',
                $log->path ?? '',
            ]);

        $csv = CsvExport::escapeRow(['created_at', 'user_name', 'user_email', 'action', 'ip_address', 'path'])."\n";
        foreach ($rows as $row) {
            $csv .= CsvExport::escapeRow($row)."\n";
        }

        return CsvExport::download($csv, 'access-logs-'.now()->format('Y-m-d-His').'.csv');
    }

    protected function filteredQuery(Request $request, $since, $until): Builder
    {
        $accountId = AccountContext::id() ?? $request->user()?->account_id;

        $query = AccessLog::with('user:id,name,email,role')
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
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

        return $query;
    }
}
