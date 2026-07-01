<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesLogDateRange;
use App\Http\Controllers\Controller;
use App\Models\LeadEvent;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ChangeLogController extends Controller
{
    use ResolvesLogDateRange;

    public function index(Request $request): InertiaResponse
    {
        [$since, $until, $days] = $this->logDateRange($request);

        $events = $this->filteredQuery($request, $since, $until)
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Logs/ChangeLogs', [
            'events' => $events,
            'filters' => [
                'days' => $days,
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'event_type' => $request->input('event_type'),
                'q' => $request->input('q'),
            ],
        ]);
    }

    public function export(Request $request): Response
    {
        [$since, $until] = $this->logDateRange($request);

        $rows = $this->filteredQuery($request, $since, $until)
            ->limit(10000)
            ->get()
            ->map(fn (LeadEvent $event) => [
                $event->created_at?->toDateTimeString(),
                $event->lead?->uuid ?? '',
                $event->lead?->campaign?->name ?? '',
                $event->event_type,
                $event->level ?? '',
                $event->message ?? '',
            ]);

        $csv = CsvExport::escapeRow(['created_at', 'lead_uuid', 'campaign', 'event_type', 'level', 'message'])."\n";
        foreach ($rows as $row) {
            $csv .= CsvExport::escapeRow($row)."\n";
        }

        return CsvExport::download($csv, 'change-logs-'.now()->format('Y-m-d-His').'.csv');
    }

    protected function filteredQuery(Request $request, $since, $until): Builder
    {
        $accountId = \App\Support\Tenancy\AccountContext::id() ?? $request->user()?->account_id;

        $query = LeadEvent::query()
            ->whereHas('lead', fn ($q) => $accountId
                ? $q->where('account_id', $accountId)
                : $q)
            ->with(['lead:id,uuid,status,campaign_id', 'lead.campaign:id,name'])
            ->whereBetween('created_at', [$since, $until])
            ->orderByDesc('created_at');

        if ($eventType = $request->input('event_type')) {
            $query->where('event_type', $eventType);
        }

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('event_type', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhereHas('lead', fn ($lq) => $lq->where('uuid', 'like', "%{$search}%"));
            });
        }

        return $query;
    }
}
