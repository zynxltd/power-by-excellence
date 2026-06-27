<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Calls\CallAnalyticsService;
use App\Services\Calls\CallWavesService;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CallLogicReportController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request, CallAnalyticsService $analytics, CallWavesService $waves): Response
    {
        $account = $this->resolveAdminAccount($request);

        $from = $request->filled('from_date') ? \Illuminate\Support\Carbon::parse($request->from_date)->startOfDay() : now()->subDays(30);
        $to = $request->filled('to_date') ? \Illuminate\Support\Carbon::parse($request->to_date)->endOfDay() : now();

        return Inertia::render('Admin/CallLogic/Reports/Index', [
            'summary' => $analytics->summary($account->id, $from, $to),
            'byCampaign' => $analytics->byCampaign($account->id, $from, $to),
            'trafficFlow' => $analytics->trafficFlow(accountId: $account->id, from: $from, to: $to),
            'waves' => $waves->suggestions($account->id),
            'filters' => [
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
            ],
        ]);
    }
}
