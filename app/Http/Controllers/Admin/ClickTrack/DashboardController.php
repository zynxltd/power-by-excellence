<?php

namespace App\Http\Controllers\Admin\ClickTrack;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Supplier;
use App\Models\TrackingConversion;
use App\Models\TrackingLink;
use App\Services\ClickTrack\ClickTrackEntitlementService;
use App\Services\ClickTrack\ClickTrackMetricsService;
use App\Services\ClickTrack\ClickTrackPendingQueueService;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesAdminAccount;

    public function __invoke(
        Request $request,
        ClickTrackEntitlementService $entitlement,
        ClickTrackPendingQueueService $pendingQueue,
    ): Response {
        $account = $this->resolveAdminAccount($request);
        $metrics = ClickTrackMetricsService::fromRequest($request);

        return Inertia::render('Admin/ClickTrack/Dashboard', [
            'entitlement' => $entitlement->summary($account),
            'summary' => $metrics->dashboardSummary(),
            'topLinks' => TrackingLink::with(['campaign:id,name', 'supplier:id,name'])
                ->withCount(['clicks', 'conversions'])
                ->orderByDesc('clicks_count')
                ->limit(5)
                ->get(),
            'pendingQueue' => $pendingQueue->conversionQueue($account?->id),
            'capAlerts' => $pendingQueue->capAlerts($account?->id),
            'filters' => [
                'days' => (int) $request->input('days', 7),
            ],
        ]);
    }
}
