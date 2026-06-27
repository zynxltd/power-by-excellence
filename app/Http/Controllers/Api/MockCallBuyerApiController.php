<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MockCallBuyerApiController extends Controller
{
    public function ping(Request $request, int $tier): JsonResponse
    {
        $floor = (float) $request->input('floor', 10);
        $cost = max($floor, 12 + $tier * 2 + random_int(0, 3));

        return response()->json([
            'Success' => true,
            'Cost' => $cost,
            'PingID' => 'call_ping_'.uniqid(),
            'tier' => $tier,
        ]);
    }
}
