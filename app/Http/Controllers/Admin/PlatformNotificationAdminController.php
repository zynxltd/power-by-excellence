<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PlatformNotification;
use App\Services\Platform\PlatformNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformNotificationAdminController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $notifications = PlatformNotification::with(['account:id,name,brand_name', 'createdBy:id,name'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
            'tenants' => Account::orderBy('name')->get(['id', 'name', 'brand_name', 'slug']),
            'severities' => ['info', 'warning', 'critical'],
        ]);
    }

    public function store(Request $request, PlatformNotificationService $service): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string|max:5000',
            'severity' => 'required|in:info,warning,critical',
            'account_id' => 'nullable|exists:accounts,id',
            'expires_at' => 'nullable|date',
        ]);

        $service->broadcast(
            $request->user(),
            $validated['title'],
            $validated['body'] ?? null,
            $validated['account_id'] ?? null,
            $validated['severity'],
            isset($validated['expires_at']) ? new \DateTime($validated['expires_at']) : null,
        );

        return back()->with('success', 'Notification sent to '.($validated['account_id'] ? 'tenant' : 'all platforms').'.');
    }

    public function update(Request $request, PlatformNotification $notification): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_unless($notification->type === 'broadcast', 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string|max:5000',
            'severity' => 'required|in:info,warning,critical',
            'expires_at' => 'nullable|date',
        ]);

        $notification->update($validated);

        return back()->with('success', 'Notification updated.');
    }

    public function destroy(Request $request, PlatformNotification $notification): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $notification->delete();

        return back()->with('success', 'Notification removed.');
    }
}
