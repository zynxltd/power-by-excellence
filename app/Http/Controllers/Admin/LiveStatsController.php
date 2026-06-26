<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LiveStats;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveStatsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->user()?->isSuperAdmin() && ! AccountContext::id()) {
            abort(403, 'Select a partner platform to view live stats.');
        }

        $campaignId = $request->integer('campaign_id') ?: null;

        return response()->json(LiveStats::snapshot($campaignId));
    }
}
