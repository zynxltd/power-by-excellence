<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LiveStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveStatsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $campaignId = $request->integer('campaign_id') ?: null;

        return response()->json(LiveStats::snapshot($campaignId));
    }
}
