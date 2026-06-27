<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Buyer;
use App\Models\Webhook;
use App\Services\Webhooks\BuyerWebhookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    public function index(): Response
    {
        $buyerWebhooks = app(BuyerWebhookService::class);

        return Inertia::render('Admin/Webhooks/Index', [
            'webhooks' => Webhook::with('buyer:id,name,reference')
                ->orderBy('name')
                ->get(),
            'buyers' => Buyer::orderBy('name')->get(['id', 'name', 'reference']),
            'eventOptions' => BuyerWebhookService::eventOptions(),
            'pendingApprovals' => $buyerWebhooks->pendingForAdmin()
                ->map(fn (Webhook $webhook) => [
                    'id' => $webhook->id,
                    'name' => $webhook->name,
                    'url' => $webhook->url,
                    'events' => $webhook->events ?? [],
                    'approval_status' => $webhook->approval_status,
                    'submitted_at' => $webhook->submitted_at?->toDateTimeString(),
                    'submission_notes' => $webhook->submission_notes,
                    'buyer' => $webhook->buyer?->only(['id', 'name', 'reference']),
                ]),
            'approvalStats' => $buyerWebhooks->adminStats(),
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

    public function update(Request $request, Webhook $webhook): RedirectResponse
    {
        if (($webhook->config['synced_from'] ?? null) === 'buyer_sold_webhook') {
            return back()->with('error', 'This webhook is managed from the buyer form.');
        }

        $webhook->update($request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2000',
            'events' => 'required|array|min:1',
            'events.*' => 'string',
            'buyer_id' => 'nullable|exists:buyers,id',
            'is_active' => 'boolean',
        ]));

        return back()->with('success', 'Webhook updated.');
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        if (($webhook->config['synced_from'] ?? null) === 'buyer_sold_webhook') {
            return back()->with('error', 'This webhook is managed from the buyer form. Edit the buyer to change or remove it.');
        }

        if ($webhook->approval_status !== null && $webhook->approval_status !== BuyerWebhookService::STATUS_APPROVED) {
            return back()->with('error', 'This webhook is awaiting buyer portal review. Approve or reject it from the queue below.');
        }

        $webhook->delete();

        return back()->with('success', 'Webhook deleted.');
    }

    public function approve(Request $request, Webhook $webhook): RedirectResponse
    {
        app(BuyerWebhookService::class)->approve($webhook, $request->user());

        return back()->with('success', 'Webhook approved and activated.');
    }

    public function reject(Request $request, Webhook $webhook): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        app(BuyerWebhookService::class)->reject(
            $webhook,
            $request->user(),
            $validated['rejection_reason'],
        );

        return back()->with('success', 'Webhook rejected. The buyer can revise and resubmit.');
    }

    public function approveDeletion(Request $request, Webhook $webhook): RedirectResponse
    {
        app(BuyerWebhookService::class)->approveDeletion($webhook, $request->user());

        return back()->with('success', 'Webhook removed.');
    }

    public function rejectDeletion(Request $request, Webhook $webhook): RedirectResponse
    {
        app(BuyerWebhookService::class)->rejectDeletion(
            $webhook,
            $request->user(),
            $request->input('rejection_reason'),
        );

        return back()->with('success', 'Deletion request rejected. Webhook remains active.');
    }
}
