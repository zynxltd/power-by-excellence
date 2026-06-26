<?php

namespace App\Services\Platform;

use App\Models\AccessLog;
use App\Models\Account;
use App\Models\DeliveryLog;
use App\Models\LeadEvent;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PlatformLiveFeed
{
    /**
     * @param  array{
     *     since?: Carbon,
     *     until?: Carbon,
     *     account_id?: int|null,
     *     type?: string|null,
     *     q?: string|null,
     * }  $filters
     */
    public function paginate(int $page = 1, int $perPage = 50, array $filters = []): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = max(10, min($perPage, 100));

        $since = $filters['since'] ?? now()->subDays(7)->startOfDay();
        $until = $filters['until'] ?? now()->endOfDay();
        $accountId = $filters['account_id'] ?? null;
        $type = $filters['type'] ?? null;
        $search = trim((string) ($filters['q'] ?? ''));

        $merged = $this->mergedFeed($since, $until, $accountId, $type, $search);
        $total = $merged->count();

        $items = $merged
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
                'pageName' => 'page',
            ]
        );
    }

    /**
     * @param  array{
     *     since?: Carbon,
     *     until?: Carbon,
     *     account_id?: int|null,
     *     type?: string|null,
     *     q?: string|null,
     * }  $filters
     * @return array{total: int, lead_events: int, deliveries: int, access: int}
     */
    public function stats(array $filters = []): array
    {
        $since = $filters['since'] ?? now()->subDays(7)->startOfDay();
        $until = $filters['until'] ?? now()->endOfDay();
        $accountId = $filters['account_id'] ?? null;
        $type = $filters['type'] ?? null;
        $search = trim((string) ($filters['q'] ?? ''));

        $merged = $this->mergedFeed($since, $until, $accountId, $type, $search);

        return [
            'total' => $merged->count(),
            'lead_events' => $merged->where('type', 'lead_event')->count(),
            'deliveries' => $merged->where('type', 'delivery')->count(),
            'access' => $merged->where('type', 'access')->count(),
        ];
    }

    /**
     * @return Collection<int, array{
     *     id: string,
     *     type: string,
     *     tenant: ?string,
     *     message: string,
     *     detail: ?string,
     *     status: ?string,
     *     actor: ?string,
     *     created_at: ?string,
     *     href: ?string,
     *     meta: array<string, mixed>
     * }>
     */
    protected function mergedFeed(
        Carbon $since,
        Carbon $until,
        ?int $accountId,
        ?string $type,
        string $search,
    ): Collection {
        $items = collect();

        if (! $type || $type === 'lead_event') {
            $items = $items->concat($this->leadEvents($since, $until, $accountId, $search));
        }

        if (! $type || $type === 'delivery') {
            $items = $items->concat($this->deliveryLogs($since, $until, $accountId, $search));
        }

        if (! $type || $type === 'access') {
            $items = $items->concat($this->accessLogs($since, $until, $accountId, $search));
        }

        return $items->sortByDesc('created_at')->values();
    }

    protected function leadEvents(Carbon $since, Carbon $until, ?int $accountId, string $search): Collection
    {
        return LeadEvent::query()
            ->with(['lead' => fn ($q) => $q->withoutGlobalScopes()->with([
                'account:id,name,brand_name',
                'campaign:id,name',
            ])])
            ->whereBetween('created_at', [$since, $until])
            ->when($accountId, fn ($q) => $q->whereHas(
                'lead',
                fn ($lead) => $lead->withoutGlobalScopes()->where('account_id', $accountId)
            ))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('message', 'like', "%{$search}%")
                        ->orWhere('event_type', 'like', "%{$search}%")
                        ->orWhereHas('lead', fn ($lead) => $lead
                            ->withoutGlobalScopes()
                            ->where('uuid', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn (LeadEvent $e) => [
                'id' => 'lead_event:'.$e->id,
                'type' => 'lead_event',
                'tenant' => $e->lead?->account?->brand_name ?: $e->lead?->account?->name,
                'message' => $e->message ?: $e->event_type,
                'detail' => $e->event_type,
                'status' => $e->level,
                'actor' => null,
                'created_at' => $e->created_at?->toDateTimeString(),
                'href' => $e->lead_id ? route('leads.show', $e->lead_id) : null,
                'meta' => array_filter([
                    'lead_uuid' => $e->lead?->uuid,
                    'campaign' => $e->lead?->campaign?->name,
                    'payload' => $e->payload,
                ]),
            ]);
    }

    protected function deliveryLogs(Carbon $since, Carbon $until, ?int $accountId, string $search): Collection
    {
        return DeliveryLog::query()
            ->with([
                'lead' => fn ($q) => $q->withoutGlobalScopes()->with('account:id,name,brand_name'),
                'delivery:id,name',
                'buyer:id,name',
            ])
            ->whereBetween('created_at', [$since, $until])
            ->when($accountId, fn ($q) => $q->whereHas(
                'lead',
                fn ($lead) => $lead->withoutGlobalScopes()->where('account_id', $accountId)
            ))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('status', 'like', "%{$search}%")
                        ->orWhereHas('delivery', fn ($d) => $d->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('buyer', fn ($b) => $b->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('lead', fn ($lead) => $lead
                            ->withoutGlobalScopes()
                            ->where('uuid', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn (DeliveryLog $log) => [
                'id' => 'delivery:'.$log->id,
                'type' => 'delivery',
                'tenant' => $log->lead?->account?->brand_name ?: $log->lead?->account?->name,
                'message' => ($log->delivery?->name ?? 'Delivery').' — '.$log->status,
                'detail' => $log->delivery?->name,
                'status' => $log->status,
                'actor' => $log->buyer?->name,
                'created_at' => $log->created_at?->toDateTimeString(),
                'href' => route('logs.delivery.show', $log->id),
                'meta' => array_filter([
                    'lead_uuid' => $log->lead?->uuid,
                    'duration_ms' => $log->duration_ms,
                    'skipped_reason' => $log->skipped_reason,
                    'revenue' => $log->revenue,
                    'http_status' => $log->http_status,
                ]),
            ]);
    }

    protected function accessLogs(Carbon $since, Carbon $until, ?int $accountId, string $search): Collection
    {
        return AccessLog::query()
            ->with(['user:id,name,email', 'account:id,name,brand_name'])
            ->whereBetween('created_at', [$since, $until])
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('action', 'like', "%{$search}%")
                        ->orWhere('path', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn (AccessLog $log) => [
                'id' => 'access:'.$log->id,
                'type' => 'access',
                'tenant' => $log->account?->brand_name ?: $log->account?->name,
                'message' => ($log->user?->name ?? 'User').' — '.$log->action,
                'detail' => $log->action,
                'status' => null,
                'actor' => $log->user?->name,
                'created_at' => $log->created_at?->toDateTimeString(),
                'href' => route('logs.access'),
                'meta' => array_filter([
                    'ip_address' => $log->ip_address,
                    'path' => $log->path,
                    'user_email' => $log->user?->email,
                ]),
            ]);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function tenants(): array
    {
        return Account::orderBy('name')
            ->get(['id', 'name', 'brand_name'])
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->brand_name ?: $a->name,
            ])
            ->all();
    }
}
