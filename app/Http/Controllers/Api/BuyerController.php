<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Buyer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyerController extends Controller
{
    public function feedback(Request $request, int $buyerId): JsonResponse
    {
        $validated = $request->validate([
            'lead_uuid' => 'required|string',
            'status' => 'required|string',
            'converted' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $buyer = Buyer::findOrFail($buyerId);
        $lead = $buyer->account->leads()->where('uuid', $validated['lead_uuid'])->firstOrFail();

        $result = app(\App\Services\Buyers\BuyerConversionService::class)->recordFeedback(
            $buyer,
            $lead,
            $validated['status'],
            $validated['converted'] ?? false,
            $validated['notes'] ?? null,
        );

        return response()->json(['status' => 'ok', 'event' => $result['event']]);
    }

    public function addCredit(Request $request, int $buyerId): JsonResponse
    {
        $validated = $request->validate(['amount' => 'required|numeric|min:0.01']);
        $buyer = Buyer::findOrFail($buyerId);

        $transaction = app(\App\Services\Billing\BuyerBillingService::class)
            ->credit($buyer, $validated['amount'], 'API top-up');

        return response()->json([
            'credit_balance' => $buyer->fresh()->credit_balance,
            'transaction_id' => $transaction->id,
        ]);
    }
}
