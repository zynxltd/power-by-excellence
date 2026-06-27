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

        return Inertia::render('Admin/CallLogic/Reports/Index', [
            'summary' => $analytics->summary($account->id),
            'byCampaign' => $analytics->byCampaign($account->id),
            'trafficFlow' => $analytics->trafficFlow(accountId: $account->id),
            'waves' => $waves->suggestions($account->id),
        ]);
    }
}
