<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformNotification;
use App\Services\Platform\PlatformNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationInboxController extends Controller
{
    public function index(Request $request, PlatformNotificationService $service): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread_count' => $service->unreadCount($user),
            'notifications' => $service->recentForUser($user, 12)->map(
                fn (PlatformNotification $n) => $service->formatForUser($n, $user)
            ),
        ]);
    }

    public function page(Request $request, PlatformNotificationService $service): Response
    {
        $user = $request->user();

        return Inertia::render('Notifications/Inbox', [
            'notifications' => $service->paginateForUser($user),
            'unreadCount' => $service->unreadCount($user),
            'isSuperAdmin' => $user->isSuperAdmin(),
        ]);
    }

    public function markRead(Request $request, PlatformNotification $notification, PlatformNotificationService $service): RedirectResponse
    {
        $service->markRead($request->user(), $notification);

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(Request $request, PlatformNotificationService $service): RedirectResponse
    {
        $count = $service->markAllRead($request->user());

        return back()->with('success', "Marked {$count} notification(s) as read.");
    }
}
