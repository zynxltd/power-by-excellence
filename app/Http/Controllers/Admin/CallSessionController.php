<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallRecording;
use App\Models\CallSession;
use App\Models\Campaign;
use App\Services\Calls\LiveCallCounterService;
use App\Services\Exports\CallExportService;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\CsvExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CallSessionController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request, LiveCallCounterService $liveCalls): Response
    {
        $account = $this->resolveAdminAccount($request);

        $query = CallSession::with(['campaign:id,name,reference', 'soldToBuyer:id,name,reference', 'trackingNumber'])
            ->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($campaignId = $request->integer('campaign_id')) {
            $query->where('campaign_id', $campaignId);
        }

        return Inertia::render('Admin/CallLogic/Calls/Index', [
            'calls' => $query->paginate(25)->withQueryString(),
            'campaigns' => Campaign::whereIn('channel', ['call', 'hybrid'])
                ->orderBy('name')
                ->get(['id', 'name', 'reference']),
            'filters' => $request->only(['status', 'campaign_id']),
            'statuses' => collect(\App\Enums\CallStatus::cases())->map->value->all(),
            'liveCallsCount' => $liveCalls->countForAccount($account->id),
        ]);
    }

    public function show(CallSession $call): Response
    {
        $call->load([
            'campaign',
            'soldToBuyer',
            'buyerTransaction',
            'trackingNumber',
            'winningDelivery.buyer',
            'events',
            'deliveryLogs.delivery',
            'deliveryLogs.buyer',
            'recordings',
            'lead',
        ]);

        $call->recordings->each(function (CallRecording $recording): void {
            $recording->setAttribute('playback_url', $recording->hasPlayback()
                ? ($recording->storage_path
                    ? route('call-logic.recordings.play', $recording)
                    : $recording->url)
                : null);
        });

        return Inertia::render('Admin/CallLogic/Calls/Show', [
            'call' => $call,
        ]);
    }

    public function export(Request $request, CallExportService $export): HttpResponse
    {
        $account = $this->resolveAdminAccount($request);

        $csv = '';
        foreach ($export->rowsForAccount($account->id, $request->only(['status', 'campaign_id', 'from_date', 'to_date'])) as $row) {
            $csv .= CsvExport::escapeRow($row)."\n";
        }

        return CsvExport::download($csv, 'calls-'.now()->format('Y-m-d-His').'.csv');
    }
}
