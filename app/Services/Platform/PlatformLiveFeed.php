<?php

namespace App\Services\Platform;

use App\Models\AccessLog;
use App\Models\DeliveryLog;
use App\Models\LeadEvent;
use Illuminate\Pagination\LengthAwarePaginator;

class PlatformLiveFeed
{
    /**
     * @return list<array{type: string, tenant: ?string, message: string, created_at: ?string, href: ?string}>
     */
    public function items(int $limit = 40): array
    {
        return $this->mergedFeed(min($limit, 500))->take($limit)->values()->all();
    }

    public function paginate(int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = max(5, min($perPage, 50));

        $total = LeadEvent::count() + DeliveryLog::count() + AccessLog::count();
        $fetchLimit = min($total, ($page * $perPage) + $perPage);

        $items = $this->mergedFeed($fetchLimit)
            ->slice(($page - 1) * $perPage, $perPage)
            ->values()
            ->all();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'feed_page',
            ]
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{type: string, tenant: ?string, message: string, created_at: ?string, href: ?string}>
     */
    protected function mergedFeed(int $limit): \Illuminate\Support\Collection
    {
        $events = LeadEvent::query()
            ->with(['lead' => fn ($q) => $q->withoutGlobalScopes()->with('account:id,name')])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (LeadEvent $e) => [
                'type' => 'lead_event',
                'tenant' => $e->lead?->account?->name,
                'message' => $e->message ?: $e->event_type,
                'created_at' => $e->created_at?->toDateTimeString(),
                'href' => $e->lead_id ? route('leads.show', $e->lead_id) : null,
            ]);

        $deliveries = DeliveryLog::query()
            ->with(['lead' => fn ($q) => $q->withoutGlobalScopes()->with('account:id,name'), 'delivery:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (DeliveryLog $log) => [
                'type' => 'delivery',
                'tenant' => $log->lead?->account?->name,
                'message' => ($log->delivery?->name ?? 'Delivery').' — '.$log->status,
                'created_at' => $log->created_at?->toDateTimeString(),
                'href' => route('logs.delivery.show', $log->id),
            ]);

        $access = AccessLog::query()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AccessLog $log) => [
                'type' => 'access',
                'tenant' => null,
                'message' => ($log->user?->name ?? 'User').' — '.$log->action,
                'created_at' => $log->created_at?->toDateTimeString(),
                'href' => route('logs.access'),
            ]);

        return $events
            ->concat($deliveries)
            ->concat($access)
            ->sortByDesc('created_at')
            ->values();
    }
}
