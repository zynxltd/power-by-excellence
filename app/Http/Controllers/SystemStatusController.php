<?php

namespace App\Http\Controllers;

use App\Services\Platform\PlatformStatusService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class SystemStatusController extends Controller
{
    public function index(PlatformStatusService $status): Response
    {
        $snapshot = $status->current();

        return Inertia::render('Marketing/Status', [
            'canLogin' => true,
            'status' => $status->publicPayload($snapshot),
            'seo' => [
                'title' => 'System Status - PowerByExcellence',
                'description' => 'Live platform health for lead distribution, ping-tree routing, and API availability.',
            ],
        ]);
    }

    public function json(PlatformStatusService $status): JsonResponse
    {
        return response()->json($status->publicPayload());
    }
}
