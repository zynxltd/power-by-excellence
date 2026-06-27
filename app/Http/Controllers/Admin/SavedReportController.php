<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\SavedReport;
use App\Services\Exports\LeadExportService;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedReportController extends Controller
{
    public function __construct(protected LeadExportService $exportService) {}

    public function index(): Response
    {
        return Inertia::render('Admin/SavedReports/Index', [
            'reports' => SavedReport::orderByDesc('created_at')->paginate(25),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'filters' => 'nullable|array',
            'columns' => 'nullable|array',
            'schedule_cron' => 'nullable|string|max:64',
            'email_recipients' => 'nullable|array',
            'email_recipients.*' => 'email',
            'status' => 'nullable|string|in:active,paused',
        ]);

        SavedReport::create(array_merge($validated, [
            'status' => $validated['status'] ?? 'active',
        ]));

        return back()->with('success', 'Saved report created.');
    }

    public function update(Request $request, SavedReport $savedReport): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'filters' => 'nullable|array',
            'columns' => 'nullable|array',
            'schedule_cron' => 'nullable|string|max:64',
            'email_recipients' => 'nullable|array',
            'email_recipients.*' => 'email',
            'status' => 'nullable|string|in:active,paused',
        ]);

        $savedReport->update($validated);

        return back()->with('success', 'Saved report updated.');
    }

    public function destroy(SavedReport $savedReport): RedirectResponse
    {
        $savedReport->delete();

        return back()->with('success', 'Saved report deleted.');
    }

    public function run(SavedReport $savedReport, \App\Services\Exports\SavedReportRunner $runner): RedirectResponse
    {
        if (! $runner->run($savedReport)) {
            return back()->with('error', 'Report run failed. Check email recipients and filters.');
        }

        return back()->with('success', 'Report exported and emailed to configured recipients.');
    }

    public function export(SavedReport $savedReport)
    {
        $query = Lead::query()->where('account_id', $savedReport->account_id);
        $query = $this->exportService->applyFilters($query, $savedReport->filters ?? []);
        $csv = $this->exportService->buildCsvFromQuery($query);

        return CsvExport::download($csv, 'report-'.now()->format('Y-m-d-His').'.csv');
    }
}
