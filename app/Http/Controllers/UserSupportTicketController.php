<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserSupportTicketController extends BaseController
{
    public function index(Request $request): Response
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->paginate(15);

        return Inertia::render('Support/Index', ['tickets' => $tickets]);
    }

    public function create(): Response
    {
        return Inertia::render('Support/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'priority' => 'in:low,normal,high',
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'account_id' => $request->user()->account_id,
            'portal_role' => $request->user()->portal_role ?? 'admin',
            'subject' => $validated['subject'],
            'priority' => $validated['priority'] ?? 'normal',
            'status' => 'open',
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'is_staff' => false,
        ]);

        return redirect()->route('support.show', $ticket)->with('success', 'Ticket created.');
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        $ticket->load(['messages.user:id,name']);

        return Inertia::render('Support/Show', ['ticket' => $ticket]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        $validated = $request->validate(['body' => 'required|string|max:5000']);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'is_staff' => false,
        ]);

        $ticket->update(['status' => 'open']);

        return back()->with('success', 'Message sent.');
    }
}
