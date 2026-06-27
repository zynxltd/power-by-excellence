<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunScheduledExportJob;
use App\Models\Buyer;
use App\Models\ScheduledExport;
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'buyer_id' => 'nullable|exists:buyers,id',
            'format' => 'nullable|string|in:csv',
            'delivery_method' => 'required|string|in:email,ftp',
            'cron' => 'nullable|string|max:64',
            'config' => 'nullable|array',
            'status' => 'nullable|string|in:active,paused',
        ]);

        ScheduledExport::create(array_merge($validated, [
            'format' => $validated['format'] ?? 'csv',
            'cron' => $validated['cron'] ?? '0 8 * * *',
            'status' => $validated['status'] ?? 'active',
        ]));

        return back()->with('success', 'Scheduled export created.');
    }

    public function update(Request $request, ScheduledExport $scheduledExport): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'buyer_id' => 'nullable|exists:buyers,id',
            'format' => 'nullable|string|in:csv',
            'delivery_method' => 'required|string|in:email,ftp',
            'cron' => 'nullable|string|max:64',
            'config' => 'nullable|array',
            'status' => 'nullable|string|in:active,paused',
        ]);

        $scheduledExport->update($validated);

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
