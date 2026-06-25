<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoutingMode;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Services\Delivery\DeliveryAnalyticsService;
use App\Support\Tenancy\AccountContext;
use App\Support\VerticalCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    public function index(Request $request, DeliveryAnalyticsService $analytics): Response
    {
        $query = Delivery::with(['campaign', 'buyer'])->withCount('logs');

        if ($accountId = AccountContext::id()) {
            $query->whereHas('campaign', fn ($q) => $q->where('account_id', $accountId));
        }

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->integer('campaign_id'));
        }
        if ($request->filled('method')) {
            $query->where('method', $request->input('method'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('buyer_id')) {
            $query->where('buyer_id', $request->integer('buyer_id'));
        }
        if ($request->filled('vertical_id')) {
            $query->whereHas('campaign', fn ($q) => $q->where('vertical_id', $request->input('vertical_id')));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        $deliveries = $query->orderByDesc('updated_at')->get()->map(function (Delivery $d) use ($analytics) {
            $array = $d->toArray();
            $array['health'] = $analytics->healthFor($d);
            $array['stats'] = $analytics->statsFor($d);

            return $array;
        });

        $grouped = $deliveries->groupBy(fn ($d) => $d['campaign']['name'] ?? 'Unassigned');

        return Inertia::render('Admin/Deliveries/Index', [
            'deliveries' => $deliveries->values(),
            'grouped' => $grouped->map(fn ($items, $campaign) => [
                'campaign' => $campaign,
                'items' => $items->values(),
            ])->values(),
            'stats' => [
                'total' => $deliveries->count(),
                'active' => $deliveries->where('status', 'active')->count(),
                'by_method' => $deliveries->groupBy('method')->map->count(),
                'healthy' => $deliveries->where('health', 'healthy')->count(),
                'warning' => $deliveries->where('health', 'warning')->count(),
                'critical' => $deliveries->where('health', 'critical')->count(),
            ],
            'filters' => $request->only(['campaign_id', 'method', 'status', 'buyer_id', 'vertical_id', 'search']),
            'filterOptions' => [
                'campaigns' => VerticalCatalog::decorateCampaigns(
                    Campaign::orderBy('name')->get(['id', 'name', 'reference', 'vertical_id'])
                ),
                'verticals' => VerticalCatalog::options(),
                'buyers' => \App\Models\Buyer::orderBy('name')->get(['id', 'name', 'reference']),
                'methods' => collect(['direct_post', 'ping_post', 'email_ping_post', 'store_lead', 'email', 'sms']),
                'statuses' => ['active', 'inactive', 'saved'],
            ],
            'view' => $request->input('view', 'cards'),
        ]);
    }

    public function show(Delivery $delivery, DeliveryAnalyticsService $analytics): Response
    {
        $delivery = $this->resolveDelivery($delivery);
        $delivery->load(['campaign', 'buyer']);
        $recentLogs = $delivery->logs()
            ->with(['lead:id,uuid,status', 'buyer:id,name'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $performance = [
            'today' => [
                'attempts' => $delivery->logs()->whereDate('created_at', today())->count(),
                'success' => $delivery->logs()->whereDate('created_at', today())->where('status', 'success')->count(),
                'revenue' => (float) $delivery->logs()->whereDate('created_at', today())->where('status', 'success')->sum('revenue'),
            ],
            'last_7_days' => [
                'attempts' => $delivery->logs()->where('created_at', '>=', now()->subDays(7))->count(),
                'success' => $delivery->logs()->where('created_at', '>=', now()->subDays(7))->where('status', 'success')->count(),
                'revenue' => (float) $delivery->logs()->where('created_at', '>=', now()->subDays(7))->where('status', 'success')->sum('revenue'),
            ],
        ];

        return Inertia::render('Admin/Deliveries/Show', [
            'delivery' => $delivery,
            'recentLogs' => $recentLogs,
            'methodGuide' => $this->methodGuides()[$delivery->method->value] ?? null,
            'health' => $analytics->healthFor($delivery),
            'stats' => $analytics->statsFor($delivery),
            'pingTreeLinks' => $analytics->pingTreeLinks($delivery),
            'performance' => $performance,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Deliveries/Form', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        Delivery::create($this->validateDelivery($request));

        return redirect()->route('deliveries.index')->with('success', 'Delivery created.');
    }

    public function edit(Delivery $delivery): Response
    {
        $delivery = $this->resolveDelivery($delivery);

        return Inertia::render('Admin/Deliveries/Form', $this->formData($delivery));
    }

    public function update(Request $request, Delivery $delivery): RedirectResponse
    {
        $delivery = $this->resolveDelivery($delivery);
        $delivery->update($this->validateDelivery($request));

        return redirect()->route('deliveries.index')->with('success', 'Delivery updated.');
    }

    public function destroy(Delivery $delivery): RedirectResponse
    {
        $delivery = $this->resolveDelivery($delivery);
        $delivery->delete();

        return redirect()->route('deliveries.index')->with('success', 'Delivery deleted.');
    }

    public function clone(Delivery $delivery): RedirectResponse
    {
        $delivery = $this->resolveDelivery($delivery);
        $copy = $delivery->replicate();
        $copy->name = $delivery->name.' (copy)';
        $copy->status = 'saved';
        $copy->save();

        return redirect()->route('deliveries.edit', $copy)->with('success', 'Delivery cloned. Review settings and activate.');
    }

    public function test(Delivery $delivery): RedirectResponse
    {
        $delivery = $this->resolveDelivery($delivery);
        $lead = $delivery->campaign->leads()->latest()->first();
        if (! $lead) {
            return back()->with('error', 'No leads available to test with. Submit a test lead via the API first.');
        }

        app(\App\Services\Delivery\DeliveryExecutor::class)->execute($lead, $delivery);

        return back()->with('success', 'Test delivery executed. Check Live Operations or lead detail for logs.');
    }

    protected function formData(?Delivery $delivery): array
    {
        $campaignContext = null;
        if ($delivery?->campaign_id) {
            $campaign = Campaign::with(['distributionConfigs' => fn ($q) => $q->where('is_active', true)])
                ->find($delivery->campaign_id);
            if ($campaign) {
                $activeConfig = $campaign->distributionConfigs->first();
                $campaignContext = [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'reference' => $campaign->reference,
                    'use_advanced_distribution' => $campaign->use_advanced_distribution,
                    'active_distribution_config_id' => $activeConfig?->id,
                    'active_distribution_config_name' => $activeConfig?->name,
                    'tier_in_config' => $this->tierForDelivery($activeConfig, $delivery->id),
                ];
            }
        }

        return [
            'delivery' => $delivery,
            'campaignContext' => $campaignContext,
            'filterFieldOptions' => $this->filterFieldOptions($delivery?->campaign_id),
            'campaigns' => VerticalCatalog::decorateCampaigns(
                Campaign::orderBy('name')->get(['id', 'name', 'reference', 'vertical_id', 'floor_price', 'bidding_mode'])
            ),
            'verticals' => VerticalCatalog::options(),
            'buyers' => \App\Models\Buyer::orderBy('name')->get(['id', 'name', 'reference']),
            'methodGuides' => $this->methodGuides(),
            'routingModes' => collect(RoutingMode::cases())->map(fn ($m) => [
                'value' => $m->value,
                'label' => str_replace('_', ' ', ucwords($m->value, '_')),
                'help' => $this->routingHelp($m->value),
            ])->values(),
            'revenueTypes' => [
                ['value' => 'fixed', 'label' => 'Fixed price', 'help' => 'Same revenue amount for every sold lead. Simplest option for new setups.'],
                ['value' => 'dynamic', 'label' => 'Dynamic (from buyer response)', 'help' => 'Revenue comes from a field in the buyer ping/post response (e.g. Cost, bid). Used for auctions.'],
                ['value' => 'rule_based', 'label' => 'Rule-based pricing', 'help' => 'Different prices based on lead field values (e.g. state=CA → £25, default → £15).'],
            ],
        ];
    }

    protected function methodGuides(): array
    {
        return [
            'direct_post' => [
                'title' => 'Direct Post (API)',
                'summary' => 'Send the full lead to a buyer CRM endpoint in one HTTP request.',
                'when' => 'Buyer accepts full lead data immediately without a ping step.',
                'icon' => 'api',
            ],
            'ping_post' => [
                'title' => 'Ping Post',
                'summary' => 'Two-step: ping partial data for a bid, then post full lead if accepted.',
                'when' => 'Buyer needs to evaluate partial data and return a price before receiving PII.',
                'icon' => 'ping',
            ],
            'store_lead' => [
                'title' => 'Store Lead',
                'summary' => 'Assign the lead to a buyer in-platform without an HTTP call.',
                'when' => 'Manual fulfillment, testing, or buyers who pull leads from the portal.',
                'icon' => 'store',
            ],
            'email' => [
                'title' => 'Email Delivery',
                'summary' => 'Email lead details to a buyer inbox using a template.',
                'when' => 'Buyer has no API — receives leads by email notification.',
                'icon' => 'email',
            ],
            'email_ping_post' => [
                'title' => 'Email Ping-Post',
                'summary' => 'Send partial non-PII lead data via email with accept/reject links.',
                'when' => 'Monetise unsold leads or buyers who prefer email over HTTP ping-post.',
                'icon' => 'email',
            ],
            'sms' => [
                'title' => 'SMS Delivery',
                'summary' => 'Send a short SMS alert with key lead fields.',
                'when' => 'Urgent buyer notifications. Requires SMS provider in production.',
                'icon' => 'sms',
            ],
        ];
    }

    protected function routingHelp(string $mode): string
    {
        return match ($mode) {
            'waterfall' => 'Try buyers in priority order until one accepts. Best for tiered fallback.',
            'parallel_auction' => 'Real-time auction: ping all buyers in parallel, highest dynamic bid above floor wins — only the winner receives the full post.',
            'sequential_ping' => 'Ping buyers one-by-one in priority order until one accepts.',
            'weighted' => 'Random selection weighted by the Weight field (e.g. 70/30 split).',
            'round_robin' => 'Rotate fairly between eligible buyers on each lead.',
            'hybrid' => 'Used inside ping-tree tier groups — configure tiers on the Ping Tree page.',
            default => '',
        };
    }

    protected function validateDelivery(Request $request): array
    {
        $request->merge([
            'advanced_distribution_only' => $request->boolean('advanced_distribution_only'),
        ]);

        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'buyer_id' => 'nullable|exists:buyers,id',
            'name' => 'required|string|max:255',
            'method' => 'required|in:direct_post,ping_post,store_lead,email,sms',
            'trigger_type' => 'in:on_lead_arrival,manual_via_api',
            'status' => 'in:active,inactive,saved',
            'priority' => 'integer|min:0',
            'weight' => 'integer|min:1',
            'tier' => 'integer|min:1',
            'routing_mode' => 'nullable|string',
            'revenue_type' => 'in:fixed,dynamic,rule_based',
            'revenue_amount' => 'numeric|min:0',
            'revenue_rules' => 'nullable|array',
            'revenue_rules.*.field' => 'nullable|string',
            'revenue_rules.*.value' => 'nullable|string',
            'revenue_rules.*.amount' => 'nullable|numeric|min:0',
            'advanced_distribution_only' => 'boolean',
            'config' => 'nullable|array',
            'caps' => 'nullable|array',
            'caps.daily' => 'nullable|integer|min:0',
            'caps.hourly' => 'nullable|integer|min:0',
            'caps.weekly' => 'nullable|integer|min:0',
            'caps.monthly' => 'nullable|integer|min:0',
            'caps.min_bid' => 'nullable|numeric|min:0',
            'caps.max_bid' => 'nullable|numeric|min:0',
            'caps.daily_spend_cap' => 'nullable|numeric|min:0',
            'caps.monthly_spend_cap' => 'nullable|numeric|min:0',
            'eligibility_rules' => 'nullable|array',
            'location_filter' => 'nullable|array',
            'location_filter.states' => 'nullable|array',
            'location_filter.zip_prefixes' => 'nullable|array',
            'location_filter.exclude_states' => 'nullable|array',
            'schedule' => 'nullable|array',
            'schedule.timezone' => 'nullable|string|max:64',
            'schedule.windows' => 'nullable|array',
            'schedule.windows.*.day' => 'nullable|string|max:16',
            'schedule.windows.*.start' => 'nullable|string|max:5',
            'schedule.windows.*.end' => 'nullable|string|max:5',
        ]);

        if (($validated['revenue_type'] ?? 'fixed') === 'rule_based') {
            $validated['revenue_rules'] = array_values(array_filter(
                $validated['revenue_rules'] ?? [],
                fn ($r) => ! empty($r['field']) && isset($r['amount'])
            ));
        }

        return $validated;
    }

    protected function tierForDelivery(?\App\Models\DistributionConfig $config, int $deliveryId): ?int
    {
        if (! $config) {
            return null;
        }

        foreach ($config->config['groups'] ?? [] as $index => $group) {
            if (in_array($deliveryId, $group['delivery_ids'] ?? [], true)) {
                return $index + 1;
            }
        }

        return null;
    }

    protected function resolveDelivery(Delivery $delivery): Delivery
    {
        if ($accountId = AccountContext::id()) {
            $campaign = Campaign::withoutGlobalScopes()->find($delivery->campaign_id);
            abort_unless($campaign && $campaign->account_id === $accountId, 404, 'Delivery not found on this platform.');
        }

        return $delivery;
    }

    /**
     * @return list<array{name: string, label: string}>
     */
    protected function filterFieldOptions(?int $campaignId): array
    {
        if (! $campaignId) {
            return [];
        }

        $campaign = Campaign::with('fields')->find($campaignId);
        if (! $campaign) {
            return [];
        }

        $fields = $campaign->fields->map(fn ($f) => [
            'name' => $f->name,
            'label' => $f->label ?: $f->name,
        ])->values()->all();

        $apiFields = collect($campaign->api_spec['fields'] ?? [])
            ->map(fn ($f) => [
                'name' => $f['name'] ?? '',
                'label' => ($f['label'] ?? $f['name'] ?? '').' (API)',
            ])
            ->filter(fn ($f) => $f['name'] !== '')
            ->values()
            ->all();

        return collect(array_merge($fields, $apiFields))
            ->unique('name')
            ->values()
            ->all();
    }
}
