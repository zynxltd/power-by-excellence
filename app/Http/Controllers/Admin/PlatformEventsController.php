<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\LeadEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformEventsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        [$since, $until, $days] = $this->dateRange($request);
        $accountId = $request->integer('account_id') ?: null;
        $level = $request->input('level');
        $category = $request->input('category');
        $eventType = $request->input('event_type');
        $search = trim((string) $request->input('q', ''));

        $base = $this->baseQuery($since, $until, $accountId);

        $query = (clone $base)
            ->when($level, fn (Builder $q) => $q->where('level', $level))
            ->when($category, fn (Builder $q) => $q->where('event_type', 'like', $category.'.%'))
            ->when($eventType, fn (Builder $q) => $q->where('event_type', $eventType))
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where(function (Builder $inner) use ($search) {
                    $inner->where('message', 'like', "%{$search}%")
                        ->orWhere('event_type', 'like', "%{$search}%")
                        ->orWhereHas('lead', fn (Builder $lead) => $lead
                            ->withoutGlobalScopes()
                            ->where('uuid', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at');

        $events = $query
            ->paginate(50)
            ->withQueryString()
            ->through(fn (LeadEvent $e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'level' => $e->level,
                'message' => $e->message,
                'payload' => $e->payload,
                'created_at' => $e->created_at?->toDateTimeString(),
                'lead_id' => $e->lead_id,
                'lead_uuid' => $e->lead?->uuid,
                'lead_status' => $e->lead?->status,
                'tenant' => $e->lead?->account?->brand_name ?: $e->lead?->account?->name,
                'tenant_id' => $e->lead?->account_id,
                'campaign' => $e->lead?->campaign?->name,
            ]);

        $statsBase = (clone $base)
            ->when($level, fn (Builder $q) => $q->where('level', $level))
            ->when($category, fn (Builder $q) => $q->where('event_type', 'like', $category.'.%'))
            ->when($eventType, fn (Builder $q) => $q->where('event_type', $eventType))
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where(function (Builder $inner) use ($search) {
                    $inner->where('message', 'like', "%{$search}%")
                        ->orWhere('event_type', 'like', "%{$search}%")
                        ->orWhereHas('lead', fn (Builder $lead) => $lead
                            ->withoutGlobalScopes()
                            ->where('uuid', 'like', "%{$search}%"));
                });
            });

        return Inertia::render('Admin/PlatformEvents/Index', [
            'events' => $events,
            'filters' => [
                'days' => $days,
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'account_id' => $accountId,
                'level' => $level,
                'category' => $category,
                'event_type' => $eventType,
                'q' => $search ?: null,
            ],
            'stats' => [
                'total' => (clone $statsBase)->count(),
                'warnings' => (clone $statsBase)->where('level', 'warning')->count(),
                'errors' => (clone $statsBase)->where('level', 'error')->count(),
                'unique_leads' => (clone $statsBase)->whereNotNull('lead_id')->distinct('lead_id')->count('lead_id'),
                'sold' => (clone $statsBase)->whereIn('event_type', ['lead.sold', 'auction.won', 'delivery.success', 'sold'])->count(),
            ],
            'tenants' => Account::orderBy('name')->get(['id', 'name', 'brand_name'])->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->brand_name ?: $a->name,
            ]),
            'levelOptions' => [
                ['value' => 'info', 'label' => 'Info'],
                ['value' => 'warning', 'label' => 'Warning'],
                ['value' => 'error', 'label' => 'Error'],
            ],
            'categoryOptions' => [
                ['value' => 'lead', 'label' => 'Lead lifecycle'],
                ['value' => 'pipeline', 'label' => 'Pipeline'],
                ['value' => 'validation', 'label' => 'Validation'],
                ['value' => 'dedupe', 'label' => 'Deduplication'],
                ['value' => 'distribution', 'label' => 'Distribution'],
                ['value' => 'delivery', 'label' => 'Delivery'],
                ['value' => 'auction', 'label' => 'Auction'],
                ['value' => 'billing', 'label' => 'Billing'],
                ['value' => 'automation', 'label' => 'Automation'],
                ['value' => 'postback', 'label' => 'Postbacks'],
            ],
            'eventTypes' => $this->eventTypes($since, $until, $accountId),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: int}
     */
    protected function dateRange(Request $request): array
    {
        $days = (int) $request->input('days', 7);
        $days = in_array($days, [1, 7, 14, 28], true) ? $days : 7;

        $since = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->subDays($days)->startOfDay();

        $until = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfDay();

        return [$since, $until, $days];
    }

    protected function baseQuery(Carbon $since, Carbon $until, ?int $accountId): Builder
    {
        return LeadEvent::query()
            ->with(['lead' => fn ($q) => $q->withoutGlobalScopes()->with([
                'account:id,name,brand_name',
                'campaign:id,name',
            ])])
            ->whereBetween('lead_events.created_at', [$since, $until])
            ->when($accountId, fn (Builder $q) => $q->whereHas(
                'lead',
                fn (Builder $lead) => $lead->withoutGlobalScopes()->where('account_id', $accountId)
            ));
    }

    /**
     * @return list<string>
     */
    protected function eventTypes(Carbon $since, Carbon $until, ?int $accountId): array
    {
        return $this->baseQuery($since, $until, $accountId)
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type')
            ->all();
    }
}
