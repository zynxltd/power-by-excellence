<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunScheduledExportJob;
use App\Models\Buyer;
use App\Models\ScheduledExport;
use App\Services\Exports\ScheduledExportFormData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduledExportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/ScheduledExports/Index', [
            'exports' => ScheduledExport::with('buyer:id,name,reference')
                ->orderByDesc('created_at')
                ->paginate(25),
            'buyers' => Buyer::orderBy('name')->get(['id', 'name', 'reference']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(ScheduledExportFormData::rules($request));

        ScheduledExport::create(ScheduledExportFormData::toAttributes($validated));

        return back()->with('success', 'Scheduled export created.');
    }

    public function update(Request $request, ScheduledExport $scheduledExport): RedirectResponse
    {
        $validated = $request->validate(ScheduledExportFormData::rules($request));

        $attributes = ScheduledExportFormData::toAttributes($validated);

        if (! filled($validated['remote_credentials'] ?? null)) {
            unset($attributes['remote_credentials']);
        }

        $scheduledExport->update($attributes);

        return back()->with('success', 'Scheduled export updated.');
    }

    public function destroy(ScheduledExport $scheduledExport): RedirectResponse
    {
        $scheduledExport->delete();

        return back()->with('success', 'Scheduled export deleted.');
    }

    public function runNow(ScheduledExport $scheduledExport): RedirectResponse
    {
        RunScheduledExportJob::dispatchSync($scheduledExport->id);

        return back()->with('success', 'Export run queued.');
    }
}
