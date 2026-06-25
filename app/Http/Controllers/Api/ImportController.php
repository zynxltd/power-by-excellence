<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Leads\CsvImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function store(Request $request, CsvImportService $importService): JsonResponse
    {
        $validated = $request->validate([
            'campaign_reference' => 'required|string',
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $accountId = $request->attributes->get('account')?->id;

        $campaign = Campaign::query()
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->where('reference', $validated['campaign_reference'])
            ->firstOrFail();

        $import = $importService->import($request->file('file'), $campaign);

        return response()->json([
            'import_id' => $import->id,
            'status' => $import->status,
            'success_rows' => $import->success_rows,
            'failed_rows' => $import->failed_rows,
        ]);
    }
}
