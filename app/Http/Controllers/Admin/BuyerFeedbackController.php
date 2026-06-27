<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Supplier;
use App\Services\Reports\BuyerFeedbackReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerFeedbackController extends Controller
{
    public function index(Request $request, BuyerFeedbackReportService $report): Response
    {
        $accountId = $report->accountId();

        $baseQuery = $report->baseQuery($accountId);
        $filteredQuery = $report->applyFilters(clone $baseQuery, $request);

        return Inertia::render('Admin/BuyerFeedback/Index', [
            'summary' => $report->summary($filteredQuery),
            'breakdowns' => [
                'suppliers' => $report->breakdownBySupplier($filteredQuery),
                'campaigns' => $report->breakdownByCampaign($filteredQuery),
                'buyers' => $report->breakdownByBuyer($filteredQuery),
                'sids' => $report->breakdownBySid($filteredQuery),
            ],
            'feedback' => $report->paginated($filteredQuery),
            'filters' => $request->only([
                'status',
                'buyer_id',
                'campaign_id',
                'supplier_id',
                'sid',
                'from_date',
                'to_date',
                'search',
            ]),
            'filterOptions' => [
                'statuses' => [
                    ['value' => 'invalid', 'label' => 'Invalid / bad lead'],
                    ['value' => 'converted', 'label' => 'Converted'],
                    ['value' => 'contacted', 'label' => 'Contacted'],
                    ['value' => 'funded', 'label' => 'Funded'],
                    ['value' => 'called', 'label' => 'Called'],
                    ['value' => 'callback', 'label' => 'Callback'],
                ],
                'campaigns' => Campaign::query()
                    ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
                    ->orderBy('name')
                    ->get(['id', 'name', 'reference']),
                'suppliers' => Supplier::query()
                    ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
                    ->orderBy('name')
                    ->get(['id', 'name', 'reference']),
                'buyers' => Buyer::query()
                    ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
                    ->orderBy('name')
                    ->get(['id', 'name', 'reference']),
            ],
        ]);
    }
}
