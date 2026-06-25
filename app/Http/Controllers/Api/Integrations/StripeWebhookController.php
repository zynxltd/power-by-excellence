<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        Log::info('Stripe webhook received', [
            'has_signature' => (bool) $signature,
            'bytes' => strlen($payload),
        ]);

        $account = Account::query()->first();
        $stripe = $account?->settings['stripe'] ?? [];

        if (! ($stripe['enabled'] ?? false)) {
            return response()->json(['error' => 'Stripe not enabled'], 403);
        }

        return response()->json(['received' => true]);
    }
}
