<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoutingMode;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\DistributionConfig;
use App\Services\Logging\PlatformLogger;
use App\Services\Security\AuditLogService;
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
                'redirect_url' => $group['redirect_url'] ?? null,
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
            'declineUrl' => $distribution->config['decline_url'] ?? null,
            'tiers' => $tiers,
            'campaign' => $campaign->only(['id', 'name', 'reference', 'use_advanced_distribution', 'floor_price']),
            'campaignWorkflow' => CampaignWorkflow::forCampaign($campaign, $distribution->id),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Distribution/Form', [
            'config' => null,
            'campaigns' => Campaign::with(['deliveries.buyer', 'fields'])->orderBy('name')->get(),
            'routingModes' => $this->routingModes(),
            'filterFieldOptions' => CampaignFieldCatalog::forCampaignId($request->integer('campaign_id') ?: null),
            'campaignWorkflow' => CampaignWorkflow::fromId($request->integer('campaign_id') ?: null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateConfig($request);
        $config = DistributionConfig::create($validated);

        $this->logConfigChange($request, 'created', $config);

        return redirect()->route('distribution.show', $config)->with('success', 'Ping tree configuration created.');
    }

    public function edit(DistributionConfig $distribution): Response
    {
        $campaign = $this->resolveCampaign($distribution);
        $campaign->load('deliveries');

        return Inertia::render('Admin/Distribution/Form', [
            'config' => $distribution,
            'campaigns' => Campaign::with(['deliveries.buyer', 'fields'])->orderBy('name')->get(),
            'routingModes' => $this->routingModes(),
            'filterFieldOptions' => CampaignFieldCatalog::forCampaign($campaign),
            'campaignWorkflow' => CampaignWorkflow::forCampaign($campaign, $distribution->id),
        ]);
    }

    public function update(Request $request, DistributionConfig $distribution): RedirectResponse
    {
        $this->ensureEditable($distribution);

        $this->resolveCampaign($distribution);
        $before = $this->configSnapshot($distribution);
        $distribution->update($this->validateConfig($request));

        $this->logConfigChange($request, 'updated', $distribution->fresh(), $before);

        return redirect()->route('distribution.show', $distribution)->with('success', 'Ping tree configuration updated.');
    }

    public function destroy(DistributionConfig $distribution): RedirectResponse
    {
        $this->ensureEditable($distribution);

        $this->resolveCampaign($distribution);
        $before = $this->configSnapshot($distribution);
        $configId = $distribution->id;
        $distribution->delete();

        PlatformLogger::info('distribution_config.deleted', [
            'distribution_config_id' => $configId,
            'campaign_id' => $before['campaign_id'] ?? null,
            'name' => $before['name'] ?? null,
            'user_id' => auth()->id(),
        ]);

        app(AuditLogService::class)->record(
            'distribution_config.deleted',
            'distribution_config',
            $configId,
            $before,
        );

        return redirect()->route('distribution.index')->with('success', 'Configuration removed.');
    }

    public function toggleLock(Request $request, DistributionConfig $distribution): RedirectResponse
    {
        $this->resolveCampaign($distribution);

        $validated = $request->validate([
            'locked' => 'required|boolean',
        ]);

        $distribution->update(['is_locked' => $validated['locked']]);

        return back()->with(
            'success',
            $validated['locked']
                ? 'Ping tree locked - editing and deletion are disabled.'
                : 'Ping tree unlocked - changes may affect live routing.',
        );
    }

    protected function ensureEditable(DistributionConfig $distribution): void
    {
        abort_if(
            $distribution->is_locked,
            422,
            'This ping tree is locked. Unlock it before making changes.',
        );
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
            'groups.*.redirect_url' => 'nullable|url|max:2048',
            'decline_url' => 'nullable|url|max:2048',
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
                'redirect_url' => filled($group['redirect_url'] ?? null) ? $group['redirect_url'] : null,
                'delivery_ids' => $group['delivery_ids'],
                'rules' => $rules,
            ], fn ($v) => $v !== null);
        })->values()->all();

        $config = ['groups' => $groups];
        if (filled($validated['decline_url'] ?? null)) {
            $config['decline_url'] = $validated['decline_url'];
        }

        return [
            'campaign_id' => $validated['campaign_id'],
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
            'config' => $config,
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

    /**
     * @return array<string, mixed>
     */
    protected function configSnapshot(DistributionConfig $config): array
    {
        $groups = $config->config['groups'] ?? [];

        return [
            'id' => $config->id,
            'name' => $config->name,
            'campaign_id' => $config->campaign_id,
            'is_active' => $config->is_active,
            'is_locked' => $config->is_locked,
            'tier_count' => count($groups),
            'delivery_count' => collect($groups)->sum(fn (array $group) => count($group['delivery_ids'] ?? [])),
        ];
    }

    protected function logConfigChange(Request $request, string $action, DistributionConfig $config, ?array $before = null): void
    {
        $snapshot = $this->configSnapshot($config);

        PlatformLogger::info("distribution_config.{$action}", [
            ...$snapshot,
            'user_id' => $request->user()?->id,
            'before' => $before,
        ]);

        app(AuditLogService::class)->record(
            "distribution_config.{$action}",
            'distribution_config',
            $config->id,
            [
                'after' => $snapshot,
                'before' => $before,
            ],
        );
    }
}
