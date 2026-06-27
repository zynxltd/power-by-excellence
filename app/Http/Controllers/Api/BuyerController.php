<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Buyer;
use App\Services\Platform\PlatformNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyerController extends Controller
{
    public function feedback(Request $request, Buyer $buyer): JsonResponse
    {
        $this->ensureBuyerBelongsToApiAccount($request, $buyer);

        $validated = $request->validate([
            'lead_uuid' => 'required|string',
            'status' => 'required|string',
            'converted' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $lead = $buyer->leads()->where('uuid', $validated['lead_uuid'])->firstOrFail();

        $result = app(\App\Services\Buyers\BuyerConversionService::class)->recordFeedback(
            $buyer,
            $lead,
            $validated['status'],
            $validated['converted'] ?? false,
            $validated['notes'] ?? null,
        );

        app(PlatformNotificationService::class)->notifyTenantBuyerFeedback(
            $buyer->account,
            null,
            $buyer,
            1,
            $validated['status'],
            $validated['converted'] ?? false,
            $validated['notes'] ?? null,
            $result['feedback_id'] ?? null,
            $result['lead_id'] ?? null,
        );

        return response()->json(['status' => 'ok', 'event' => $result['event']]);
    }

    public function addCredit(Request $request, Buyer $buyer): JsonResponse
    {
        $this->ensureBuyerBelongsToApiAccount($request, $buyer);

        $validated = $request->validate(['amount' => 'required|numeric|min:0.01']);

        $transaction = app(\App\Services\Billing\BuyerBillingService::class)
            ->credit($buyer, $validated['amount'], 'API top-up');

        return response()->json([
            'credit_balance' => $buyer->fresh()->credit_balance,
            'transaction_id' => $transaction->id,
        ]);
    }

    protected function ensureBuyerBelongsToApiAccount(Request $request, Buyer $buyer): void
    {
        $accountId = $request->attributes->get('account')?->id;

        if ($accountId && $buyer->account_id !== $accountId) {
            abort(404);
        }
    }
}
