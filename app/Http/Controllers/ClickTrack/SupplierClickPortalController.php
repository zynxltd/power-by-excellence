<?php

namespace App\Http\Controllers\ClickTrack;

use App\Http\Controllers\Controller;
use App\Services\ClickTrack\SupplierClickPayoutExportService;
use App\Services\ClickTrack\SupplierClickStatsService;
use App\Services\Suppliers\SupplierPortalService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SupplierClickPortalController extends Controller
{
    public function __construct(
        protected SupplierPortalService $portal,
        protected SupplierClickStatsService $stats,
        protected SupplierClickPayoutExportService $payoutExporter,
    ) {}

    public function __invoke(Request $request): InertiaResponse
    {
        $supplier = $request->user()->supplier;
        abort_unless($supplier, 403);
        $account = $request->user()->account ?? $supplier->account;

        return Inertia::render('Portal/Supplier/Clicks', [
            'supplier' => $supplier->only(['id', 'name', 'reference']),
            'account' => $this->portal->accountSummary($supplier),
            'stats' => $this->stats->forSupplier($supplier),
            'currency' => $account?->default_currency ?? 'GBP',
        ]);
    }

    public function export(Request $request): Response
    {
        $supplier = $request->user()->supplier;
        abort_unless($supplier, 403);

        return $this->payoutExporter->download($supplier, $request);
    }
}
