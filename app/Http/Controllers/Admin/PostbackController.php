<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Postback;
use App\Models\PostbackLog;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostbackController extends Controller
{
    public function index(): Response
    {
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
            'eventOptions' => [
                'lead.accepted',
                'lead.sold',
                'lead.rejected',
                'lead.unsold',
                'lead.contacted',
                'lead.converted',
                'lead.funded',
                'lead.returned',
                'delivery.success',
            ],
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
        $postback->delete();

        return back()->with('success', 'Postback removed.');
    }
}
