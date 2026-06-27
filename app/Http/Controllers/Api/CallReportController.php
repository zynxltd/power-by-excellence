<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Calls\CallAnalyticsService;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallReportController extends Controller
{
    public function index(Request $request, CallAnalyticsService $analytics): JsonResponse
    {
        $accountId = AccountContext::id();

        return response()->json([
            'summary' => $analytics->summary($accountId),
            'by_campaign' => $analytics->byCampaign($accountId),
            'traffic_flow' => $analytics->trafficFlow(accountId: $accountId),
        ]);
    }
}
