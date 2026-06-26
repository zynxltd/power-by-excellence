<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignField;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\Admin\CampaignWorkflow;
use App\Support\Admin\TenantHub;
use App\Support\VerticalCatalog;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Campaigns/Index', [
            'campaigns' => Campaign::with('account')
                ->withCount('leads')
                ->orderBy('name')
                ->paginate(25),
            'showTenantColumn' => ! AccountContext::id() && $request->user()?->isSuperAdmin(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->resolveAdminAccount($request);

        return Inertia::render('Admin/Campaigns/Form', [
            'campaign' => null,
            'defaults' => $this->accountDefaults($request),
            'verticals' => VerticalCatalog::options(),
            'biddingModes' => $this->biddingModes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);
        AccountContext::set($account);
        $request->attributes->set('account', $account);

        $this->prepareCampaignPayload($request);
        $validated = $this->validateCampaign($request);
        $validated['account_id'] = $account->id;

        $campaign = Campaign::create($validated);

        foreach (VerticalCatalog::fieldsFor($validated['vertical_id'] ?? null) as $i => $field) {
            CampaignField::create(array_merge($field, ['campaign_id' => $campaign->id, 'sort_order' => $i]));
        }

        $account = $campaign->account;
        if ($account && $request->user()) {
            app(\App\Services\Platform\PlatformNotificationService::class)->logTenantActivity(
                $account,
                $request->user(),
                'campaign.created',
                'Campaign created',
                "Campaign \"{$campaign->name}\" ({$campaign->reference}) was created.",
                ['campaign_id' => $campaign->id]
            );
        }

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign created.');
    }

    public function show(Request $request, Campaign $campaign): Response
    {
        $campaign->load(['fields', 'distributionConfigs', 'account']);

        $deliveries = $campaign->deliveries()
            ->with('buyer:id,name,reference')
            ->orderByRaw('tier IS NULL, tier ASC')
            ->orderBy('priority')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15), ['*'], 'delivery_page')
            ->withQueryString();

        return Inertia::render('Admin/Campaigns/Show', [
            'campaign' => $campaign,
            'deliveries' => $deliveries,
            'tenantHub' => TenantHub::forAccount($campaign->account, $campaign->id),
            'campaignWorkflow' => CampaignWorkflow::forCampaign($campaign),
            'leadsToday' => $campaign->leads()->whereDate('received_at', today())->count(),
        ]);
    }

    public function edit(Request $request, Campaign $campaign): Response
    {
        $campaign->load('account');

        return Inertia::render('Admin/Campaigns/Form', [
            'campaign' => $campaign,
            'defaults' => $this->accountDefaults($request),
            'verticals' => VerticalCatalog::options(),
            'biddingModes' => $this->biddingModes(),
            'tenantHub' => TenantHub::forAccount($campaign->account, $campaign->id),
            'campaignWorkflow' => CampaignWorkflow::forCampaign($campaign),
            'activeDistributionConfigId' => $campaign->distributionConfigs()->where('is_active', true)->value('id'),
        ]);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->resolveAdminAccount($request);
        $this->prepareCampaignPayload($request);
        $validated = $this->validateCampaign($request, $campaign);

        $campaign->update($validated);

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign updated.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaign deleted.');
    }

    public function updateValidation(Request $request, Campaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'dedupe_config' => 'nullable|array',
            'dedupe_config.fields' => 'nullable|array',
            'dedupe_config.reject_days' => 'nullable|integer|min:1',
            'validation_config' => 'nullable|array',
            'validation_config.require_email' => 'boolean',
            'validation_config.require_phone' => 'boolean',
            'validation_config.block_disposable_email' => 'boolean',
        ]);

        $campaign->update([
            'dedupe_config' => array_merge($campaign->dedupe_config ?? [], $validated['dedupe_config'] ?? []),
            'validation_config' => array_merge($campaign->validation_config ?? [], $validated['validation_config'] ?? []),
        ]);

        return back()->with('success', 'Validation rules updated.');
    }

    protected function validateCampaign(Request $request, ?Campaign $campaign = null): array
    {
        $request->merge([
            'country' => strtoupper(trim((string) $request->input('country', ''))),
            'currency' => strtoupper(trim((string) $request->input('currency', ''))),
            'reference' => strtolower(trim((string) $request->input('reference', ''))),
            'use_advanced_distribution' => $request->boolean('use_advanced_distribution'),
        ]);

        $accountId = AccountContext::id()
            ?? $campaign?->account_id
            ?? $this->resolveOptionalAdminAccount($request)?->id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'reference' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('campaigns', 'reference')
                    ->where(fn ($q) => $q->where('account_id', $accountId))
                    ->ignore($campaign?->id),
            ],
            'type' => 'nullable|in:standard,suppression',
            'country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'status' => 'nullable|in:active,inactive,archived',
            'vertical_id' => 'nullable|string|max:100',
            'payout_amount' => 'required|numeric|min:0',
            'floor_price' => 'required|numeric|min:0',
            'bidding_mode' => 'nullable|in:waterfall,real_time_auction,dynamic_ping',
            'sell_mode' => 'nullable|in:exclusive,multi_sell',
            'use_advanced_distribution' => 'boolean',
            'caps' => 'nullable|array',
            'caps.daily' => 'nullable|integer|min:0',
            'caps.hourly' => 'nullable|integer|min:0',
            'caps.daily_spend_cap' => 'nullable|numeric|min:0',
            'caps.monthly_spend_cap' => 'nullable|numeric|min:0',
            'dedupe_config' => 'nullable|array',
        ], [
            'name.required' => 'Campaign name is required.',
            'reference.required' => 'API reference is required.',
            'reference.regex' => 'Reference may only contain letters, numbers, hyphens and underscores.',
            'reference.unique' => 'This reference is already used on your platform.',
            'country.regex' => 'Country must be a 2-letter ISO code (e.g. GB, US).',
            'country.size' => 'Country must be exactly 2 letters.',
            'currency.regex' => 'Currency must be a 3-letter ISO code (e.g. GBP, USD).',
            'currency.size' => 'Currency must be exactly 3 letters.',
            'payout_amount.required' => 'Payout amount is required.',
            'floor_price.required' => 'Floor price is required.',
        ]);

        if (empty($validated['vertical_id'])) {
            $validated['vertical_id'] = null;
        }

        $validated['caps'] = array_filter($validated['caps'] ?? [], fn ($v) => $v !== null && $v !== '');

        return $validated;
    }

    protected function prepareCampaignPayload(Request $request): void
    {
        $caps = $request->input('caps');
        if (! is_array($caps)) {
            return;
        }

        $request->merge([
            'caps' => array_map(
                fn ($value) => $value === '' ? null : $value,
                $caps,
            ),
            'vertical_id' => $request->input('vertical_id') ?: null,
        ]);
    }

    protected function biddingModes(): array
    {
        return [
            ['value' => 'real_time_auction', 'label' => 'Real-time auction', 'help' => 'Ping all buyers in parallel; highest bid above floor wins, then post only to winner.'],
            ['value' => 'dynamic_ping', 'label' => 'Dynamic ping', 'help' => 'Each buyer returns a dynamic bid via ping-post; standard waterfall routing.'],
            ['value' => 'waterfall', 'label' => 'Waterfall (fixed)', 'help' => 'Priority order with fixed or rule-based pricing — no parallel bidding.'],
        ];
    }

    protected function accountDefaults(Request $request): array
    {
        $account = $request->attributes->get('account') ?? $request->user()?->account;

        return [
            'country' => $account?->default_country ?? 'GB',
            'currency' => $account?->default_currency ?? 'GBP',
        ];
    }

    protected function defaultFields(string $country): array
    {
        $postcodeLabel = in_array($country, ['US', 'CA']) ? 'Zip Code' : 'Postcode';

        return [
            ['name' => 'firstname', 'label' => 'First Name', 'required' => true, 'ping_field' => false],
            ['name' => 'lastname', 'label' => 'Last Name', 'required' => true, 'ping_field' => false],
            ['name' => 'email', 'label' => 'Email', 'required' => true, 'ping_field' => false],
            ['name' => 'phone1', 'label' => 'Phone', 'required' => true, 'ping_field' => false],
            ['name' => 'zipcode', 'label' => $postcodeLabel, 'required' => true, 'ping_field' => true],
            ['name' => 'state', 'label' => 'State / Region', 'required' => false, 'ping_field' => true],
        ];
    }
}
