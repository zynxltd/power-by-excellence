<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\HostedForm;
use App\Models\Supplier;
use App\Services\Forms\HostedFormEmbedService;
use App\Support\VerticalCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FormBuilderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Forms/Index', [
            'forms' => HostedForm::with('campaign:id,name,reference,vertical_id')->orderByDesc('updated_at')->paginate(20),
            'campaigns' => VerticalCatalog::decorateCampaigns(
                Campaign::orderBy('name')->get(['id', 'name', 'reference', 'vertical_id'])
            ),
            'verticals' => VerticalCatalog::options(),
            'fieldTypes' => $this->fieldTypes(),
        ]);
    }

    public function edit(HostedForm $hostedForm): Response
    {
        $hostedForm->load('campaign.fields', 'account');

        $campaignForms = HostedForm::where('campaign_id', $hostedForm->campaign_id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $embedService = app(HostedFormEmbedService::class);
        $sampleParams = array_filter([
            'sid' => $hostedForm->config['default_sid'] ?? 'your_sid',
            'ssid' => 'your_subid',
            'click_id' => 'affiliate_click_id',
        ]);
        $account = $hostedForm->account ?? $hostedForm->campaign?->account;
        $iframeEmbedEnabled = $embedService->accountAllowsSupplierIframeEmbed($account);

        return Inertia::render('Admin/Forms/Edit', [
            'form' => $hostedForm,
            'fieldTypes' => $this->fieldTypes(),
            'apiSpec' => $spec = app(\App\Services\Api\CampaignApiSpecService::class)->defaultSpec($hostedForm->campaign),
            'specFieldOptions' => collect($spec['fields'] ?? [])->map(fn ($f) => [
                'name' => $f['name'],
                'label' => $f['label'],
                'type' => $f['form_type'] ?? 'text',
                'required' => (bool) ($f['required'] ?? false),
                'api_type' => $f['type'] ?? 'string',
            ])->values(),
            'campaignForms' => $campaignForms,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name', 'reference']),
            'supplierIframeEmbedEnabled' => $iframeEmbedEnabled,
            'embed' => $embedService->embedPayload($hostedForm, $sampleParams),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateForm($request);
        $campaign = Campaign::with('account')->findOrFail($validated['campaign_id']);

        $form = HostedForm::create([
            ...$validated,
            'account_id' => $campaign->account_id,
            'is_active' => true,
        ]);

        $account = $campaign->account;
        if ($account && $request->user()) {
            app(\App\Services\Platform\PlatformNotificationService::class)->logTenantActivity(
                $account,
                $request->user(),
                'form.created',
                'Hosted form created',
                "Form \"{$form->name}\" was created for campaign {$campaign->name}.",
                ['form_id' => $form->id, 'campaign_id' => $campaign->id]
            );
        }

        return redirect()->route('forms.edit', $form)->with('success', 'Form created — add steps and fields.');
    }

    public function update(Request $request, HostedForm $hostedForm): RedirectResponse
    {
        $validated = $this->validateForm($request);
        $this->assertFieldsMatchApiSpec($hostedForm->campaign, $validated['config']['steps'] ?? []);

        $hostedForm->update($validated);

        return back()->with('success', 'Form saved.');
    }

    public function destroy(HostedForm $hostedForm): RedirectResponse
    {
        $hostedForm->delete();

        return back()->with('success', 'Form removed.');
    }

    protected function validateForm(Request $request): array
    {
        return $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'config' => 'nullable|array',
            'config.redirect_url' => 'nullable|url',
            'config.allowed_domains' => 'nullable|array',
            'config.allowed_domains.*' => 'string|max:255',
            'config.default_supplier_id' => 'nullable|integer|exists:suppliers,id',
            'config.default_sid' => 'nullable|string|max:100',
            'config.embed_height' => 'nullable|integer|min:320|max:2000',
            'config.css' => 'nullable|string',
            'config.thank_you' => 'nullable|array',
            'config.thank_you.mode' => 'nullable|in:inline,redirect',
            'config.thank_you.title' => 'nullable|string|max:255',
            'config.thank_you.message' => 'nullable|string|max:2000',
            'config.thank_you.show_reference' => 'boolean',
            'config.thank_you.button_text' => 'nullable|string|max:100',
            'config.thank_you.confetti' => 'boolean',
            'config.multi_step' => 'boolean',
            'config.steps' => 'nullable|array',
            'config.steps.*.id' => 'required_with:config.steps|string',
            'config.steps.*.title' => 'required_with:config.steps|string',
            'config.steps.*.description' => 'nullable|string',
            'config.steps.*.fields' => 'nullable|array',
            'config.steps.*.fields.*.name' => 'required|string',
            'config.steps.*.fields.*.label' => 'required|string',
            'config.steps.*.fields.*.type' => 'required|in:text,email,tel,number,radio,select,checkbox,textarea,date,postcode',
            'config.steps.*.fields.*.required' => 'boolean',
            'config.steps.*.fields.*.options' => 'nullable|array',
        ]);
    }

    protected function fieldTypes(): array
    {
        return [
            ['value' => 'text', 'label' => 'Text'],
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

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    protected function assertFieldsMatchApiSpec(Campaign $campaign, array $steps): void
    {
        $spec = app(\App\Services\Api\CampaignApiSpecService::class)->defaultSpec($campaign);
        $specNames = collect($spec['fields'] ?? [])->pluck('name')->all();
        $formNames = collect($steps)->flatMap(fn ($s) => $s['fields'] ?? [])->pluck('name')->filter()->all();

        $unknown = array_values(array_diff($formNames, $specNames));
        if ($unknown !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'config' => 'Form fields not in API spec: '.implode(', ', $unknown).'. Add them to the API spec or remove from the form.',
            ]);
        }
    }
}
