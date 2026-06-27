<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Postback;
use App\Models\PostbackLog;
use App\Models\Supplier;
use App\Services\Postbacks\SupplierPostbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostbackController extends Controller
{
    public function index(): Response
    {
        $supplierPostbacks = app(SupplierPostbackService::class);

        return Inertia::render('Admin/Postbacks/Index', [
            'postbacks' => Postback::with(['supplier:id,name', 'campaign:id,name'])
                ->withCount('logs')
                ->orderBy('name')
                ->paginate(25),
            'recentLogs' => PostbackLog::with(['postback:id,name', 'lead:id,uuid'])
                ->orderByDesc('created_at')
                ->limit(15)
                ->get(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name', 'reference']),
            'campaigns' => Campaign::orderBy('name')->get(['id', 'name', 'reference']),
            'eventOptions' => SupplierPostbackService::eventOptions(),
            'pendingApprovals' => $supplierPostbacks->pendingForAdmin()
                ->map(fn (Postback $postback) => [
                    'id' => $postback->id,
                    'name' => $postback->name,
                    'url' => $postback->url,
                    'method' => $postback->method,
                    'events' => $postback->events ?? [],
                    'approval_status' => $postback->approval_status,
                    'submitted_at' => $postback->submitted_at?->toDateTimeString(),
                    'submission_notes' => $postback->submission_notes,
                    'supplier' => $postback->supplier?->only(['id', 'name', 'reference']),
                    'campaign' => $postback->campaign?->only(['id', 'name', 'reference']),
                ]),
            'approvalStats' => $supplierPostbacks->adminStats(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Postback::create($request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|string|max:2000',
            'method' => 'required|in:get,post',
            'events' => 'required|array|min:1',
            'events.*' => 'string',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'is_active' => 'boolean',
        ]));

        return back()->with('success', 'Postback created.');
    }

    public function update(Request $request, Postback $postback): RedirectResponse
    {
        $postback->update($request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|string|max:2000',
            'method' => 'required|in:get,post',
            'events' => 'required|array|min:1',
            'events.*' => 'string',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'is_active' => 'boolean',
        ]));

        return back()->with('success', 'Postback updated.');
    }

    public function destroy(Postback $postback): RedirectResponse
    {
        if (($postback->config['synced_from'] ?? null) === 'supplier_default_postback') {
            return back()->with('error', 'This postback is managed from the supplier form. Edit the supplier to change or remove it.');
        }

        $postback->delete();

        return back()->with('success', 'Postback removed.');
    }

    public function approve(Request $request, Postback $postback): RedirectResponse
    {
        app(SupplierPostbackService::class)->approve($postback, $request->user());

        return back()->with('success', 'Postback approved and activated.');
    }

    public function reject(Request $request, Postback $postback): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        app(SupplierPostbackService::class)->reject(
            $postback,
            $request->user(),
            $validated['rejection_reason'],
        );

        return back()->with('success', 'Postback rejected. The supplier can revise and resubmit.');
    }

    public function approveDeletion(Request $request, Postback $postback): RedirectResponse
    {
        app(SupplierPostbackService::class)->approveDeletion($postback, $request->user());

        return back()->with('success', 'Postback removed.');
    }

    public function rejectDeletion(Request $request, Postback $postback): RedirectResponse
    {
        app(SupplierPostbackService::class)->rejectDeletion(
            $postback,
            $request->user(),
            $request->input('rejection_reason'),
        );

        return back()->with('success', 'Deletion request rejected. Postback remains active.');
    }
}
