<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Webhooks/Index', [
            'webhooks' => Webhook::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Webhook::create($request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'events' => 'required|array',
            'events.*' => 'string',
            'is_active' => 'boolean',
        ]));

        return back()->with('success', 'Webhook created.');
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        $webhook->delete();

        return back()->with('success', 'Webhook deleted.');
    }
}
