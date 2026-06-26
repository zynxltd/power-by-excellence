<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Buyer;
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
            'webhooks' => Webhook::with('buyer:id,name,reference')
                ->orderBy('name')
                ->get(),
            'buyers' => Buyer::orderBy('name')->get(['id', 'name', 'reference']),
            'eventOptions' => [
                'lead.accepted',
                'lead.sold',
                'lead.rejected',
                'lead.unsold',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Webhook::create($request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2000',
            'events' => 'required|array|min:1',
            'events.*' => 'string',
            'buyer_id' => 'nullable|exists:buyers,id',
            'is_active' => 'boolean',
        ]));

        return back()->with('success', 'Webhook created.');
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        if (($webhook->config['synced_from'] ?? null) === 'buyer_sold_webhook') {
            return back()->with('error', 'This webhook is managed from the buyer form. Edit the buyer to change or remove it.');
        }

        $webhook->delete();

        return back()->with('success', 'Webhook deleted.');
    }
}
