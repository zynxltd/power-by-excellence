<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\Platform\PlatformNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserSupportTicketController extends BaseController
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->isSuperAdmin()) {
            return redirect()->route('support.admin.index');
        }

        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->paginate(15);

        return Inertia::render('Support/Index', ['tickets' => $tickets]);
    }

    public function create(Request $request): Response
    {
        abort_if($request->user()?->isSuperAdmin(), 403);

        return Inertia::render('Support/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()?->isSuperAdmin(), 403);
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

        $account = $request->user()->account;
        if ($account) {
            app(PlatformNotificationService::class)->notifySupportTenantMessage(
                $account,
                $request->user(),
                $ticket,
                'support.ticket_created',
                $validated['body'],
            );
        }

        return redirect()->route('support.show', $ticket)->with('success', 'Ticket created.');
    }

    public function show(Request $request, SupportTicket $ticket): Response|RedirectResponse
    {
        if ($request->user()?->isSuperAdmin()) {
            return redirect()->route('support.admin.show', $ticket);
        }

        abort_unless($ticket->user_id === $request->user()->id, 403);
        $ticket->load(['messages.user:id,name']);

        return Inertia::render('Support/Show', ['ticket' => $ticket]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        if ($request->user()?->isSuperAdmin()) {
            return redirect()->route('support.admin.show', $ticket);
        }

        abort_unless($ticket->user_id === $request->user()->id, 403);

        $validated = $request->validate(['body' => 'required|string|max:5000']);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'is_staff' => false,
        ]);

        $ticket->update(['status' => 'open']);

        $account = $request->user()->account;
        if ($account) {
            app(PlatformNotificationService::class)->notifySupportTenantMessage(
                $account,
                $request->user(),
                $ticket,
                'support.tenant_reply',
                $validated['body'],
            );
        }

        return back()->with('success', 'Message sent.');
    }
}
