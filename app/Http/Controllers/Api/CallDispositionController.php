<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Services\Calls\CallRouter;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallDispositionController extends Controller
{
    public function store(Request $request, string $uuid, CallRouter $router): JsonResponse
    {
        $session = CallSession::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'disposition' => 'required|string|max:64',
            'duration_seconds' => 'nullable|integer|min:0',
            'connected' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $updated = $router->recordDisposition($session, $validated);

        return response()->json([
            'success' => true,
            'call_uuid' => $updated->uuid,
            'status' => $updated->status->value,
            'billable_seconds' => $updated->billable_seconds,
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        $session = CallSession::where('uuid', $uuid)
            ->with(['soldToBuyer:id,name,reference', 'campaign:id,name,reference'])
            ->firstOrFail();

        return response()->json([
            'uuid' => $session->uuid,
            'status' => $session->status->value,
            'caller_number' => $session->caller_number,
            'duration_seconds' => $session->duration_seconds,
            'disposition' => $session->disposition,
            'revenue' => $session->revenue,
            'buyer' => $session->soldToBuyer,
            'campaign' => $session->campaign,
        ]);
    }
}
