<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\LeadImport;
use App\Services\Leads\CsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Imports/Index', [
            'imports' => LeadImport::with('campaign')->orderByDesc('created_at')->paginate(20),
            'campaigns' => Campaign::orderBy('name')->get(['id', 'name', 'reference']),
        ]);
    }

    public function store(Request $request, CsvImportService $importService): RedirectResponse
    {
        if ($request->input('type') === 'suppression') {
            $validated = $request->validate([
                'campaign_id' => 'required|exists:campaigns,id',
                'field' => 'required|string|max:64',
                'file' => 'required|file|mimes:csv,txt|max:10240',
            ]);
            $campaign = Campaign::findOrFail($validated['campaign_id']);
            $count = app(\App\Services\Leads\SuppressionImportService::class)->import(
                $campaign,
                $request->file('file'),
                $validated['field']
            );

            return redirect()->route('imports.index')->with('success', "Suppression list imported: {$count} hashes.");
        }

        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $campaign = Campaign::findOrFail($validated['campaign_id']);
        $import = $importService->import($request->file('file'), $campaign, $request->user()->id);

        return redirect()->route('imports.index')
            ->with('success', "Import complete: {$import->success_rows} succeeded, {$import->failed_rows} failed.");
    }
}
