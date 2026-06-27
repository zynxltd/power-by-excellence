<?php

namespace App\Http\Controllers\Admin\ClickTrack;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Supplier;
use App\Services\ClickTrack\ClickTrackEntitlementService;
use App\Services\ClickTrack\ClickTrackMetricsService;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request, ClickTrackEntitlementService $entitlement): Response
    {
        $account = $this->resolveAdminAccount($request);
        $metrics = ClickTrackMetricsService::fromRequest($request);
        $tab = $request->input('tab', 'performance');

        return Inertia::render('Admin/ClickTrack/Reports/Index', [
            'entitlement' => $entitlement->summary($account),
            'tab' => $tab,
            'summary' => $metrics->dashboardSummary(),
            'performance' => $metrics->performanceRows(),
            'conversionStatus' => $metrics->conversionStatusByDate(),
            'campaigns' => Campaign::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['days', 'date_from', 'date_to', 'campaign_id', 'supplier_id', 'group_by', 'tab']),
            'groupOptions' => [
                ['value' => 'offer', 'label' => 'By offer'],
                ['value' => 'affiliate', 'label' => 'By affiliate'],
                ['value' => 'date', 'label' => 'By date'],
            ],
        ]);
    }
}
