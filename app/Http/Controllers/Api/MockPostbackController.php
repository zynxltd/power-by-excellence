<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MockPostbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Log::info('Mock postback received', [
            'method' => $request->method(),
            'query' => $request->query(),
            'body' => $request->except(['password', 'token']),
        ]);

        return response()->json([
            'ok' => true,
            'received_at' => now()->toIso8601String(),
        ]);
    }
}
