<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\SavedReport;
use App\Services\Exports\LeadExportService;
use App\Services\Exports\SavedReportSchedule;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SavedReportController extends Controller
{
    public function __construct(protected LeadExportService $exportService) {}

    public function index(): Response
    {
        $reports = SavedReport::query()
            ->orderByDesc('created_at')
            ->paginate(25)
            ->through(fn (SavedReport $report) => array_merge($report->toArray(), [
                'schedule_preset' => SavedReportSchedule::presetForCron($report->schedule_cron),
            ]));

        return Inertia::render('Admin/SavedReports/Index', [
            'reports' => $reports,
            'schedulePresets' => collect(SavedReportSchedule::presets())
                ->map(fn (array $preset, string $key) => [
                    'value' => $key,
                    'label' => $preset['label'],
                    'cron' => $preset['cron'],
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateReport($request);

        SavedReport::create(array_merge($validated, [
            'status' => $validated['status'] ?? 'active',
        ]));

        return back()->with('success', 'Saved report created.');
    }

    public function update(Request $request, SavedReport $savedReport): RedirectResponse
    {
        $validated = $this->validateReport($request);

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

    /**
     * @return array<string, mixed>
     */
    protected function validateReport(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'filters' => 'nullable|array',
            'columns' => 'nullable|array',
            'schedule_preset' => 'nullable|string|max:32',
            'schedule_cron' => 'nullable|string|max:64',
            'email_recipients' => 'nullable|array',
            'email_recipients.*' => 'email',
            'status' => 'nullable|string|in:active,paused',
        ]);

        try {
            $validated = SavedReportSchedule::applyScheduleAttributes($validated);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'schedule_cron' => $e->getMessage(),
            ]);
        }

        if (filled($validated['schedule_cron'] ?? null) && empty($validated['email_recipients'] ?? [])) {
            throw ValidationException::withMessages([
                'email_recipients' => 'Add at least one email recipient when scheduling a report.',
            ]);
        }

        return $validated;
    }
}
