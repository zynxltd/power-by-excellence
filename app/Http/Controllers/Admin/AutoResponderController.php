<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutoResponder;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutoResponderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Features/AutoResponders', [
            'responders' => AutoResponder::with('campaign:id,name')->orderBy('name')->get(),
            'campaigns' => Campaign::orderBy('name')->get(['id', 'name', 'reference']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AutoResponder::create($request->validate([
            'campaign_id' => 'nullable|exists:campaigns,id',
            'name' => 'required|string|max:255',
            'channel' => 'required|in:email,sms',
            'trigger_event' => 'required|in:on_lead_received,on_lead_sold',
            'status' => 'in:active,inactive',
            'config' => 'nullable|array',
            'config.subject' => 'nullable|string|max:255',
            'config.body' => 'nullable|string',
            'config.to_field' => 'nullable|string|max:64',
        ]));

        return back()->with('success', 'Auto responder created.');
    }

    public function destroy(AutoResponder $autoResponder): RedirectResponse
    {
        $autoResponder->delete();

        return back()->with('success', 'Auto responder removed.');
    }
}
