<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Services\Calls\CallReturnService;
use App\Services\Exports\CallExportService;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BuyerCallPortalController extends Controller
{
    public function index(Request $request): Response
    {
        $buyer = $this->resolveBuyer($request);

        $query = CallSession::where('sold_to_buyer_id', $buyer->id)
            ->with(['campaign:id,name,reference'])
            ->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return Inertia::render('Portal/Buyer/Calls', [
            'calls' => $query->paginate(25)->withQueryString(),
            'filters' => $request->only(['status']),
            'buyer' => $buyer->only(['id', 'name']),
        ]);
    }

    public function show(Request $request, CallSession $call): Response
    {
        $buyer = $this->resolveBuyer($request);
        abort_unless($call->sold_to_buyer_id === $buyer->id, 403);

        $call->load(['campaign', 'events', 'recordings', 'callReturn']);

        $returnService = app(CallReturnService::class);

        return Inertia::render('Portal/Buyer/CallShow', [
            'call' => $call,
            'buyer' => $buyer->only(['id', 'name']),
            'canSubmitReturn' => $returnService->canSubmit($call, $buyer),
            'returnWindowDays' => $returnService->returnWindowDays($call),
        ]);
    }

    public function submitReturn(Request $request, CallSession $call, CallReturnService $returns): RedirectResponse
    {
        $buyer = $this->resolveBuyer($request);
        abort_unless($call->sold_to_buyer_id === $buyer->id, 403);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $returns->submit($call, $buyer, $validated['reason']);

        return back()->with('success', 'Return submitted for review.');
    }

    public function export(Request $request, CallExportService $export): HttpResponse
    {
        $buyer = $this->resolveBuyer($request);

        $csv = '';
        foreach ($export->rowsForBuyer($buyer->id, $request->only(['status', 'from_date', 'to_date'])) as $row) {
            $csv .= CsvExport::escapeRow($row)."\n";
        }

        return CsvExport::download($csv, 'buyer-calls-'.now()->format('Y-m-d').'.csv');
    }

    protected function resolveBuyer(Request $request)
    {
        return $request->user()->buyer ?? abort(403);
    }
}
