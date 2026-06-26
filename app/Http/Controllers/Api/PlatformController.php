<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Api\PlatformExportService;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    public function show(Request $request, PlatformExportService $export): JsonResponse
    {
        /** @var Account $account */
        $account = $request->attributes->get('account');

        $include = $request->filled('include')
            ? array_values(array_filter(array_map('trim', explode(',', $request->string('include')->toString()))))
            : null;

        return response()->json($export->export($account, $include));
    }

    public function campaign(Request $request, string $reference, PlatformExportService $export): JsonResponse
    {
        /** @var Account $account */
        $account = $request->attributes->get('account');

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'platform' => [
                'slug' => $account->slug,
                'api_base_url' => TenantResolver::apiBaseUrl($account),
            ],
            'campaign' => $export->campaign($account, $reference),
        ]);
    }
}
