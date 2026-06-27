<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Calls\CallRouter;
use App\Services\Calls\DniService;
use App\Models\Account;
use App\Models\TrackingNumber;
use App\Support\Products\CallLogicProduct;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallDniController extends Controller
{
    public function resolve(Request $request, DniService $dni): JsonResponse
    {
        $validated = $request->validate([
            'account_slug' => 'required|string',
            'campaign_id' => 'nullable|integer',
            'sid' => 'nullable|string',
            'ssid' => 'nullable|string',
            'pool' => 'nullable|string',
        ]);

        $account = Account::where('slug', $validated['account_slug'])->firstOrFail();

        if (! CallLogicProduct::isEnabled($account)) {
            return response()->json(['error' => 'Call Logic not enabled'], 403);
        }

        AccountContext::set($account);

        $number = $dni->resolve($account->id, $validated['campaign_id'] ?? null, $validated);

        if (! $number) {
            return response()->json(['error' => 'No tracking number available'], 404);
        }

        return response()->json([
            'phone_number' => $number->phone_number,
            'tracking_number_id' => $number->id,
            'campaign_id' => $number->campaign_id,
        ]);
    }
}
