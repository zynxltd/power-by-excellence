<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformLiveFeed;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiveFeedController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        [$since, $until, $days] = $this->dateRange($request);

        $filters = [
            'since' => $since,
            'until' => $until,
            'account_id' => $request->integer('account_id') ?: null,
            'type' => $request->input('type') ?: null,
            'q' => trim((string) $request->input('q', '')) ?: null,
        ];

        $feed = app(PlatformLiveFeed::class);

        return Inertia::render('Admin/LiveFeed/Index', [
            'liveFeed' => $feed->paginate($request->integer('page', 1), 50, $filters)->withQueryString(),
            'stats' => $feed->stats($filters),
            'filters' => [
                'days' => $days,
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'account_id' => $filters['account_id'],
                'type' => $filters['type'],
                'q' => $filters['q'],
            ],
            'tenants' => $feed->tenants(),
            'typeOptions' => [
                ['value' => 'lead_event', 'label' => 'Lead events'],
                ['value' => 'delivery', 'label' => 'Deliveries'],
                ['value' => 'access', 'label' => 'Access'],
            ],
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: int}
     */
    protected function dateRange(Request $request): array
    {
        $days = (int) $request->input('days', 1);
        $days = in_array($days, [1, 7, 14, 28], true) ? $days : 1;

        $since = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->subDays($days)->startOfDay();

        $until = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfDay();

        return [$since, $until, $days];
    }
}
