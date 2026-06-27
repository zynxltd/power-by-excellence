<?php

namespace App\Http\Controllers\Admin\ClickTrack;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Supplier;
use App\Models\TrackingConversion;
use App\Services\ClickTrack\ClickTrackEntitlementService;
use App\Services\ClickTrack\ClickTrackPendingQueueService;
use App\Services\ClickTrack\ConversionTrackingService;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversionController extends Controller
{
    use ResolvesAdminAccount;

    public function index(
        Request $request,
        ClickTrackEntitlementService $entitlement,
        ClickTrackPendingQueueService $pendingQueue,
    ): Response {
        $account = $this->resolveAdminAccount($request);

        $query = TrackingConversion::with([
            'trackingLink:id,name',
            'campaign:id,name',
            'supplier:id,name',
            'buyer:id,name',
            'lead:id,uuid,status',
            'trackingClick:id,click_uuid',
        ])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->integer('campaign_id'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }

        return Inertia::render('Admin/ClickTrack/Conversions/Index', [
            'entitlement' => $entitlement->summary($account),
            'conversions' => $query->paginate(50)->withQueryString(),
            'pendingQueue' => $pendingQueue->conversionQueue($account?->id, 5),
            'campaigns' => Campaign::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['status', 'campaign_id', 'supplier_id']),
            'statusOptions' => [
                TrackingConversion::STATUS_PENDING,
                TrackingConversion::STATUS_APPROVED,
                TrackingConversion::STATUS_REJECTED,
            ],
        ]);
    }

    public function approve(Request $request, TrackingConversion $trackingConversion, ConversionTrackingService $conversions): RedirectResponse
    {
        $this->resolveAdminAccount($request);
        $conversions->approve($trackingConversion, $trackingConversion->lead);

        return back()->with('success', 'Conversion approved.');
    }

    public function reject(Request $request, TrackingConversion $trackingConversion, ConversionTrackingService $conversions): RedirectResponse
    {
        $this->resolveAdminAccount($request);
        $validated = $request->validate(['reason' => 'nullable|string|max:500']);
        $conversions->reject($trackingConversion, $trackingConversion->lead, $validated['reason'] ?? 'Rejected by admin');

        return back()->with('success', 'Conversion rejected.');
    }

    public function bulkApprove(Request $request, ConversionTrackingService $conversions): RedirectResponse
    {
        $this->resolveAdminAccount($request);
        $validated = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);

        $items = TrackingConversion::whereIn('id', $validated['ids'])
            ->where('status', TrackingConversion::STATUS_PENDING)
            ->get();

        foreach ($items as $conversion) {
            $conversions->approve($conversion, $conversion->lead);
        }

        return back()->with('success', count($items).' conversion(s) approved.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->resolveAdminAccount($request);

        $rows = TrackingConversion::with(['trackingLink', 'campaign', 'supplier', 'buyer'])
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get()
            ->map(fn (TrackingConversion $c) => [
                $c->created_at?->toDateTimeString(),
                $c->trackingLink?->name ?? $c->campaign?->name,
                $c->supplier?->name,
                $c->goal,
                $c->status,
                $c->payout,
                $c->revenue,
                $c->sale_amount,
                $c->conversion_uuid,
                $c->trackingClick?->click_uuid,
            ]);

        return CsvExport::stream('conversions-report.csv', [
            'Date', 'Offer', 'Affiliate', 'Goal', 'Status', 'Payout', 'Revenue', 'Sale', 'Conversion ID', 'Click ID',
        ], $rows);
    }
}
