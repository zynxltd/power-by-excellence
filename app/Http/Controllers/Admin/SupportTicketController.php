<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\Platform\PlatformNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $query = SupportTicket::withoutGlobalScopes()
            ->with(['user:id,name,email', 'account:id,name,brand_name,slug', 'assignee:id,name'])
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
        $ticket = SupportTicket::withoutGlobalScopes()
            ->with(['messages.user:id,name,email', 'user:id,name,email', 'account:id,name,brand_name,slug'])
            ->findOrFail($ticket->id);

        return Inertia::render('Admin/Support/Show', [
            'ticket' => $ticket,
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket = SupportTicket::withoutGlobalScopes()->findOrFail($ticket->id);

        $validated = $request->validate(['body' => 'required|string|max:5000']);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'is_staff' => true,
        ]);

        $ticket->update(['status' => 'pending']);

        app(PlatformNotificationService::class)->notifySupportStaffReply(
            $request->user(),
            $ticket,
            $validated['body'],
        );

        return back()->with('success', 'Reply sent.');
    }

    public function updateStatus(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket = SupportTicket::withoutGlobalScopes()->findOrFail($ticket->id);

        $validated = $request->validate(['status' => 'required|in:open,pending,resolved,closed']);

        $ticket->update([
            'status' => $validated['status'],
            'closed_at' => $validated['status'] === 'closed' ? now() : null,
        ]);

        return back()->with('success', 'Ticket updated.');
    }
}
