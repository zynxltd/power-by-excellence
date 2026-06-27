<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ClickTrack\ClickTrackMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClickTrackReportController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $metrics = ClickTrackMetricsService::fromRequest($request);

        return response()->json($metrics->dashboardSummary());
    }

    public function performance(Request $request): JsonResponse
    {
        $metrics = ClickTrackMetricsService::fromRequest($request);

        return response()->json([
            'rows' => $metrics->performanceRows(),
        ]);
    }
}
