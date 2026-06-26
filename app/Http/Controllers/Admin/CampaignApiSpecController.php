<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\HostedForm;
use App\Services\Api\CampaignApiSpecService;
use App\Support\Admin\CampaignWorkflow;
use App\Support\Admin\TenantHub;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignApiSpecController extends Controller
{
    public function edit(Campaign $campaign, CampaignApiSpecService $specService): Response
    {
        $campaign->load(['fields', 'account']);
        $spec = $specService->defaultSpec($campaign);
        $sample = $specService->sampleRequest($campaign, $spec);

        $activeConfig = $campaign->distributionConfigs()->where('is_active', true)->first();
        $apiBaseUrl = TenantResolver::apiBaseUrl($campaign->account);
        $tenantRoot = rtrim(TenantResolver::portalUrl($campaign->account, ''), '/');

        return Inertia::render('Admin/Campaigns/ApiSpec', [
            'campaign' => $campaign->only(['id', 'name', 'reference', 'vertical_id', 'currency', 'country', 'account_id']),
            'campaignAccount' => $campaign->account ? [
                'id' => $campaign->account->id,
                'name' => $campaign->account->brand_name ?: $campaign->account->name,
            ] : null,
            'activeDistributionConfigId' => $activeConfig?->id,
            'spec' => $spec,
            'sampleRequest' => $sample,
            'sampleResponse' => $specService->sampleResponse(),
            'sampleStatusResponse' => $specService->sampleStatusResponse(),
            'curl' => $specService->buildCurl($tenantRoot, null, $spec, $sample),
            'fieldTypes' => $this->fieldTypes(),
            'formTypes' => $this->formTypes(),
            'verticals' => \App\Support\VerticalCatalog::options(),
            'apiBaseUrl' => $apiBaseUrl,
            'tenantHost' => TenantResolver::portalHost($campaign->account),
            'premadeTemplates' => $this->premadeTemplates(),
            'tenantHub' => TenantHub::forAccount($campaign->account, $campaign->id),
            'campaignWorkflow' => CampaignWorkflow::forCampaign($campaign, $activeConfig?->id),
        ]);
    }

    public function update(Request $request, Campaign $campaign, CampaignApiSpecService $specService): RedirectResponse
    {
        $this->ensureApiSpecEditable($campaign, $specService);

        $validated = $request->validate([
            'spec' => 'required|array',
            'spec.version' => 'nullable|string',
            'spec.description' => 'nullable|string',
            'spec.fields' => 'required|array|min:1',
            'spec.fields.*.name' => 'required|string|max:64|regex:/^[a-z][a-z0-9_]*$/',
            'spec.fields.*.label' => 'required|string|max:255',
            'spec.fields.*.type' => 'required|in:string,email,phone,number,postcode,date,boolean,enum',
            'spec.fields.*.required' => 'boolean',
            'spec.fields.*.ping_field' => 'boolean',
            'spec.fields.*.description' => 'nullable|string|max:500',
            'spec.fields.*.example' => 'nullable|string|max:255',
            'spec.fields.*.enum' => 'nullable|array',
            'spec.fields.*.form_type' => 'nullable|in:text,email,tel,number,postcode,radio,select,checkbox,textarea,date',
            'sync_fields' => 'boolean',
        ]);

        $spec = array_merge($specService->defaultSpec($campaign), $validated['spec']);
        $spec['locked'] = $specService->isLocked($campaign);
        $spec['fields'] = collect($spec['fields'])->map(
            fn (array $f, int $i) => $specService->normalizeField($f, $i)
        )->values()->all();

        $campaign->update(['api_spec' => $spec]);

        if ($request->boolean('sync_fields')) {
            $specService->syncFieldsToCampaign($campaign, $spec);
        }

        return back()->with('success', 'API spec saved'.($request->boolean('sync_fields') ? ' and campaign fields synced.' : '.'));
    }

    public function toggleLock(Request $request, Campaign $campaign, CampaignApiSpecService $specService): RedirectResponse
    {
        $validated = $request->validate([
            'locked' => 'required|boolean',
        ]);

        $spec = $specService->defaultSpec($campaign);
        $spec['locked'] = $validated['locked'];
        $campaign->update(['api_spec' => $spec]);

        return back()->with(
            'success',
            $validated['locked']
                ? 'API spec locked — editing and saving are disabled.'
                : 'API spec unlocked — changes may affect live integrations.',
        );
    }

    public function applyToForm(Request $request, Campaign $campaign, CampaignApiSpecService $specService): RedirectResponse
    {
        $this->ensureApiSpecEditable($campaign, $specService);

        $validated = $request->validate([
            'hosted_form_id' => 'required|exists:hosted_forms,id',
        ]);

        $form = HostedForm::findOrFail($validated['hosted_form_id']);
        abort_unless($form->campaign_id === $campaign->id, 422);

        $spec = $specService->defaultSpec($campaign);
        $steps = $specService->specToFormSteps($spec);

        $config = $form->config ?? [];
        $config['steps'] = $steps;
        $config['multi_step'] = count($steps) > 1;
        $config['imported_from_api_spec'] = true;
        $config['imported_at'] = now()->toIso8601String();

        $form->update(['config' => $config]);

        return redirect()->route('forms.edit', $form)->with('success', 'Form fields generated from API spec.');
    }

    public function loadVerticalTemplate(Request $request, Campaign $campaign, CampaignApiSpecService $specService): RedirectResponse
    {
        $this->ensureApiSpecEditable($campaign, $specService);

        $validated = $request->validate([
            'vertical_id' => 'required|string',
        ]);

        $templateFields = \App\Support\VerticalCatalog::fieldsFor($validated['vertical_id']);
        $spec = $specService->defaultSpec($campaign);
        $spec['fields'] = collect($templateFields)->map(
            fn (array $f, int $i) => $specService->normalizeField($f, $i)
        )->values()->all();
        $spec['description'] = 'Template from '.\App\Support\VerticalCatalog::label($validated['vertical_id']);
        $spec['locked'] = false;

        $campaign->update(['api_spec' => $spec, 'vertical_id' => $validated['vertical_id']]);

        return back()->with('success', 'Loaded vertical template into API spec.');
    }

    public function loadPremadeTemplate(Request $request, Campaign $campaign, CampaignApiSpecService $specService): RedirectResponse
    {
        $this->ensureApiSpecEditable($campaign, $specService);

        $validated = $request->validate([
            'template_key' => 'required|string',
        ]);

        $template = collect($this->premadeTemplates())->firstWhere('key', $validated['template_key']);
        abort_unless($template, 422, 'Unknown template.');

        $spec = array_merge($specService->defaultSpec($campaign), [
            'description' => $template['description'],
            'locked' => false,
            'fields' => collect($template['fields'])->map(
                fn (array $f, int $i) => $specService->normalizeField($f, $i)
            )->values()->all(),
        ]);

        $campaign->update(['api_spec' => $spec]);

        return back()->with('success', "Loaded template: {$template['name']}");
    }

    protected function premadeTemplates(): array
    {
        return [
            [
                'key' => 'solar_full',
                'name' => 'Solar — full lead',
                'description' => 'Standard UK solar lead with roof and usage fields',
                'fields' => [
                    ['name' => 'firstname', 'label' => 'First name', 'type' => 'string', 'required' => true, 'ping_field' => true],
                    ['name' => 'lastname', 'label' => 'Last name', 'type' => 'string', 'required' => true, 'ping_field' => true],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                    ['name' => 'phone1', 'label' => 'Phone', 'type' => 'phone', 'required' => true, 'ping_field' => true],
                    ['name' => 'zipcode', 'label' => 'Postcode', 'type' => 'postcode', 'required' => true, 'ping_field' => true],
                    ['name' => 'roof_type', 'label' => 'Roof type', 'type' => 'enum', 'enum' => ['pitched', 'flat', 'unknown']],
                    ['name' => 'monthly_bill', 'label' => 'Monthly bill', 'type' => 'number'],
                ],
            ],
            [
                'key' => 'auto_insurance',
                'name' => 'Auto insurance',
                'description' => 'US auto insurance quote request',
                'fields' => [
                    ['name' => 'firstname', 'label' => 'First name', 'type' => 'string', 'required' => true, 'ping_field' => true],
                    ['name' => 'lastname', 'label' => 'Last name', 'type' => 'string', 'required' => true],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                    ['name' => 'phone1', 'label' => 'Phone', 'type' => 'phone', 'required' => true, 'ping_field' => true],
                    ['name' => 'zipcode', 'label' => 'ZIP', 'type' => 'postcode', 'required' => true, 'ping_field' => true],
                    ['name' => 'state', 'label' => 'State', 'type' => 'string', 'required' => true, 'ping_field' => true],
                    ['name' => 'vehicle_year', 'label' => 'Vehicle year', 'type' => 'number', 'required' => true],
                ],
            ],
            [
                'key' => 'payday_ping',
                'name' => 'Payday — ping-minimal',
                'description' => 'Minimal PII for ping-post waterfall',
                'fields' => [
                    ['name' => 'zipcode', 'label' => 'Postcode', 'type' => 'postcode', 'required' => true, 'ping_field' => true],
                    ['name' => 'loan_amount', 'label' => 'Loan amount', 'type' => 'number', 'required' => true, 'ping_field' => true],
                    ['name' => 'employment_status', 'label' => 'Employment', 'type' => 'enum', 'enum' => ['employed', 'self_employed', 'unemployed'], 'ping_field' => true],
                    ['name' => 'firstname', 'label' => 'First name', 'type' => 'string', 'required' => true],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                    ['name' => 'phone1', 'label' => 'Phone', 'type' => 'phone', 'required' => true],
                ],
            ],
            [
                'key' => 'mortgage_standard',
                'name' => 'Mortgage enquiry',
                'description' => 'Mortgage lead with income and property value',
                'fields' => [
                    ['name' => 'firstname', 'label' => 'First name', 'type' => 'string', 'required' => true],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                    ['name' => 'phone1', 'label' => 'Phone', 'type' => 'phone', 'required' => true, 'ping_field' => true],
                    ['name' => 'zipcode', 'label' => 'Postcode', 'type' => 'postcode', 'required' => true, 'ping_field' => true],
                    ['name' => 'property_value', 'label' => 'Property value', 'type' => 'number', 'required' => true],
                    ['name' => 'loan_amount', 'label' => 'Loan amount', 'type' => 'number', 'required' => true, 'ping_field' => true],
                ],
            ],
        ];
    }

    protected function ensureApiSpecEditable(Campaign $campaign, CampaignApiSpecService $specService): void
    {
        abort_if(
            $specService->isLocked($campaign),
            422,
            'API spec is locked. Unlock it before making changes.',
        );
    }

    protected function fieldTypes(): array
    {
        return [
            ['value' => 'string', 'label' => 'String'],
            ['value' => 'email', 'label' => 'Email'],
            ['value' => 'phone', 'label' => 'Phone'],
            ['value' => 'number', 'label' => 'Number'],
            ['value' => 'postcode', 'label' => 'Postcode / ZIP'],
            ['value' => 'date', 'label' => 'Date'],
            ['value' => 'boolean', 'label' => 'Boolean'],
            ['value' => 'enum', 'label' => 'Enum (options)'],
        ];
    }

    protected function formTypes(): array
    {
        return [
            ['value' => 'text', 'label' => 'Text input'],
            ['value' => 'email', 'label' => 'Email'],
            ['value' => 'tel', 'label' => 'Phone'],
            ['value' => 'number', 'label' => 'Number'],
            ['value' => 'postcode', 'label' => 'Postcode'],
            ['value' => 'radio', 'label' => 'Radio buttons'],
            ['value' => 'select', 'label' => 'Dropdown'],
            ['value' => 'checkbox', 'label' => 'Checkbox'],
            ['value' => 'textarea', 'label' => 'Long text'],
            ['value' => 'date', 'label' => 'Date'],
        ];
    }
}
