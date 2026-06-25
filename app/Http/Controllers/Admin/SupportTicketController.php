<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $query = SupportTicket::with(['user:id,name,email', 'assignee:id,name'])
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return Inertia::render('Admin/Support/Index', [
            'tickets' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['status']),
            'statuses' => ['open', 'pending', 'resolved', 'closed'],
        ]);
    }

    public function show(SupportTicket $ticket): Response
    {
        $ticket->load(['messages.user:id,name,email', 'user:id,name,email']);

        return Inertia::render('Admin/Support/Show', [
            'ticket' => $ticket,
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate(['body' => 'required|string|max:5000']);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'is_staff' => true,
        ]);

        $ticket->update(['status' => 'pending']);

        return back()->with('success', 'Reply sent.');
    }

    public function updateStatus(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate(['status' => 'required|in:open,pending,resolved,closed']);

        $ticket->update([
            'status' => $validated['status'],
            'closed_at' => $validated['status'] === 'closed' ? now() : null,
        ]);

        return back()->with('success', 'Ticket updated.');
    }
}
