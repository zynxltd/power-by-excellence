<?php

namespace App\Http\Controllers\Admin\ClickTrack;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Supplier;
use App\Models\TrackingClick;
use App\Services\ClickTrack\ClickTrackEntitlementService;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\CsvExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClickController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request, ClickTrackEntitlementService $entitlement): Response
    {
        $account = $this->resolveAdminAccount($request);

        $query = TrackingClick::with(['trackingLink:id,name', 'campaign:id,name', 'supplier:id,name', 'lead:id,uuid,status'])
            ->orderByDesc('clicked_at');

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->integer('campaign_id'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }

        if ($request->filled('unique_only')) {
            $query->where('is_unique', true);
        }

        return Inertia::render('Admin/ClickTrack/Clicks/Index', [
            'entitlement' => $entitlement->summary($account),
            'clicks' => $query->paginate(50)->withQueryString(),
            'campaigns' => Campaign::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['campaign_id', 'supplier_id', 'unique_only']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->resolveAdminAccount($request);

        $rows = TrackingClick::with(['trackingLink', 'campaign', 'supplier'])
            ->orderByDesc('clicked_at')
            ->limit(5000)
            ->get()
            ->map(fn (TrackingClick $click) => [
                $click->clicked_at?->toDateTimeString(),
                $click->trackingLink?->name,
                $click->campaign?->name,
                $click->supplier?->name,
                $click->click_uuid,
                $click->sub1,
                $click->is_unique ? 'yes' : 'no',
                $click->ip_address,
                $click->country,
                $click->device,
            ]);

        return CsvExport::stream('clicks-report.csv', [
            'Date', 'Offer', 'Campaign', 'Affiliate', 'Click ID', 'Sub1', 'Unique', 'IP', 'Country', 'Device',
        ], $rows);
    }
}
