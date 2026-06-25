<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoutingMode;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\DistributionConfig;
use App\Support\Admin\CampaignWorkflow;
use App\Support\Campaign\CampaignFieldCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DistributionController extends Controller
{
    public function index(Request $request): Response
    {
        $query = DistributionConfig::query()
            ->forTenant()
            ->with(['campaign.deliveries'])
            ->whereHas('campaign');

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->integer('campaign_id'));
        }
        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $configs = $query->orderByDesc('updated_at')->get();

        $campaigns = Campaign::withCount('distributionConfigs')
            ->orderBy('name')
            ->get(['id', 'name', 'reference', 'use_advanced_distribution', 'distribution_configs_count']);

        return Inertia::render('Admin/Distribution/Index', [
            'configs' => $configs,
            'campaigns' => $campaigns,
            'routingModes' => collect(RoutingMode::cases())->map(fn ($m) => [
                'value' => $m->value,
                'label' => str_replace('_', ' ', ucfirst($m->value)),
            ]),
            'filters' => $request->only(['campaign_id', 'active']),
            'filterOptions' => [
                'campaigns' => Campaign::orderBy('name')->get(['id', 'name']),
            ],
            'campaignWorkflow' => CampaignWorkflow::fromId($request->integer('campaign_id') ?: null),
        ]);
    }

    public function show(DistributionConfig $distribution): Response
    {
        $campaign = $this->resolveCampaign($distribution);
        $campaign->load(['deliveries.buyer']);
        $deliveriesById = $campaign->deliveries->keyBy('id');

        $tiers = collect($distribution->config['groups'] ?? [])->map(function (array $group, int $index) use ($deliveriesById) {
            $deliveryIds = $group['delivery_ids'] ?? [];

            return [
                'tier' => $index + 1,
                'name' => $group['name'] ?? 'Tier '.($index + 1),
                'mode' => $group['mode'] ?? 'waterfall',
                'floor_price' => $group['floor_price'] ?? null,
                'rules' => $group['rules'] ?? null,
                'deliveries' => collect($deliveryIds)->map(function ($id) use ($deliveriesById) {
                    $d = $deliveriesById->get($id);

                    return $d ? [
                        'id' => $d->id,
                        'name' => $d->name,
                        'method' => $d->method->value,
                        'buyer' => $d->buyer?->name,
                        'priority' => $d->priority,
                        'weight' => $d->weight,
                        'status' => $d->status,
                    ] : ['id' => $id, 'name' => 'Missing delivery', 'missing' => true];
                })->values(),
            ];
        });

        return Inertia::render('Admin/Distribution/Show', [
            'config' => $distribution,
            'tiers' => $tiers,
            'campaign' => $campaign->only(['id', 'name', 'reference', 'use_advanced_distribution', 'floor_price']),
            'campaignWorkflow' => CampaignWorkflow::forCampaign($campaign, $distribution->id),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Distribution/Form', [
            'config' => null,
            'campaigns' => Campaign::with(['deliveries', 'fields'])->orderBy('name')->get(),
            'routingModes' => $this->routingModes(),
            'filterFieldOptions' => CampaignFieldCatalog::forCampaignId($request->integer('campaign_id') ?: null),
            'campaignWorkflow' => CampaignWorkflow::fromId($request->integer('campaign_id') ?: null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateConfig($request);
        $config = DistributionConfig::create($validated);

        return redirect()->route('distribution.show', $config)->with('success', 'Ping tree configuration created.');
    }

    public function edit(DistributionConfig $distribution): Response
    {
        $campaign = $this->resolveCampaign($distribution);
        $campaign->load('deliveries');

        return Inertia::render('Admin/Distribution/Form', [
            'config' => $distribution,
            'campaigns' => Campaign::with(['deliveries', 'fields'])->orderBy('name')->get(),
            'routingModes' => $this->routingModes(),
            'filterFieldOptions' => CampaignFieldCatalog::forCampaign($campaign),
            'campaignWorkflow' => CampaignWorkflow::forCampaign($campaign, $distribution->id),
        ]);
    }

    public function update(Request $request, DistributionConfig $distribution): RedirectResponse
    {
        $this->resolveCampaign($distribution);
        $distribution->update($this->validateConfig($request));

        return redirect()->route('distribution.show', $distribution)->with('success', 'Ping tree configuration updated.');
    }

    public function destroy(DistributionConfig $distribution): RedirectResponse
    {
        $this->resolveCampaign($distribution);
        $distribution->delete();

        return redirect()->route('distribution.index')->with('success', 'Configuration removed.');
    }

    protected function validateConfig(Request $request): array
    {
        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'groups' => 'required|array|min:1',
            'groups.*.name' => 'required|string|max:255',
            'groups.*.mode' => 'required|string',
            'groups.*.floor_price' => 'nullable|numeric|min:0',
            'groups.*.delivery_ids' => 'required|array|min:1',
            'groups.*.delivery_ids.*' => 'integer',
            'groups.*.rules' => 'nullable|array',
        ]);

        $groups = collect($validated['groups'])->map(function (array $group) {
            $rules = $group['rules'] ?? null;
            if (is_array($rules) && empty($rules['conditions'])) {
                $rules = null;
            }

            return array_filter([
                'name' => $group['name'],
                'mode' => $group['mode'],
                'floor_price' => $group['floor_price'] ?? null,
                'delivery_ids' => $group['delivery_ids'],
                'rules' => $rules,
            ], fn ($v) => $v !== null);
        })->values()->all();

        return [
            'campaign_id' => $validated['campaign_id'],
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
            'config' => ['groups' => $groups],
        ];
    }

    protected function routingModes(): array
    {
        return collect(RoutingMode::cases())->map(fn ($m) => [
            'value' => $m->value,
            'label' => str_replace('_', ' ', ucfirst($m->value)),
        ])->all();
    }

    protected function resolveCampaign(DistributionConfig $distribution): Campaign
    {
        $campaign = Campaign::query()->find($distribution->campaign_id);

        abort_unless($campaign, 404, 'Ping tree campaign not found or not accessible on this platform.');

        return $campaign;
    }
}
