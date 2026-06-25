<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformNotification;
use App\Services\Platform\PlatformNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationInboxController extends Controller
{
    public function index(Request $request, PlatformNotificationService $service): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread_count' => $service->unreadCount($user),
            'notifications' => $service->recentForUser($user, 12)->map(fn (PlatformNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'severity' => $n->severity,
                'type' => $n->type,
                'is_read' => (bool) $n->getAttribute('is_read'),
                'account' => $n->account ? [
                    'id' => $n->account->id,
                    'name' => $n->account->brand_name ?: $n->account->name,
                ] : null,
                'created_at' => $n->created_at?->toDateTimeString(),
            ]),
        ]);
    }

    public function markRead(Request $request, PlatformNotification $notification, PlatformNotificationService $service): RedirectResponse
    {
        $service->markRead($request->user(), $notification);

        return back();
    }

    public function markAllRead(Request $request, PlatformNotificationService $service): RedirectResponse
    {
        $count = $service->markAllRead($request->user());

        return back()->with('success', "Marked {$count} notification(s) as read.");
    }
}
