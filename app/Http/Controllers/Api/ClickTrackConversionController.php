<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrackingLink;
use App\Services\ClickTrack\ConversionTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversionController extends Controller
{
    public function store(Request $request, ConversionTrackingService $conversions): JsonResponse
    {
        $validated = $request->validate([
            'link_token' => 'required_without:tracking_link_id|string',
            'tracking_link_id' => 'required_without:link_token|integer',
            'click_id' => 'nullable|uuid',
            'status' => 'nullable|in:pending,approved,rejected',
            'goal' => 'nullable|string|max:100',
            'payout' => 'nullable|numeric|min:0',
            'revenue' => 'nullable|numeric|min:0',
            'sale_amount' => 'nullable|numeric|min:0',
            'external_id' => 'nullable|string|max:255',
            'rejected_reason' => 'nullable|string|max:500',
        ]);

        $accountId = $request->attributes->get('api_key')?->account_id;

        $linkQuery = TrackingLink::withoutGlobalScopes()->where('account_id', $accountId);

        $link = isset($validated['link_token'])
            ? $linkQuery->where('token', $validated['link_token'])->firstOrFail()
            : $linkQuery->where('id', $validated['tracking_link_id'])->firstOrFail();

        $conversion = $conversions->recordInbound($link, $validated);

        return response()->json([
            'conversion_uuid' => $conversion->conversion_uuid,
            'status' => $conversion->status,
        ], 201);
    }
}
