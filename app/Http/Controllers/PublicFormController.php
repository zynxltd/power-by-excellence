<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use App\Jobs\ProcessLeadJob;
use App\Models\HostedForm;
use App\Services\Forms\HostedFormEmbedService;
use App\Services\Leads\LeadIngestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicFormController extends BaseController
{
    public function __construct(
        protected HostedFormEmbedService $embedService,
    ) {}

    public function show(Request $request, string $slug): Response
    {
        $form = HostedForm::withoutGlobalScopes()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $this->embedService->assertEmbedAllowed($form, $request);

        return Inertia::render('Forms/Show', $this->formPageProps($request, $slug));
    }

    public function submit(Request $request, string $slug): Response|RedirectResponse
    {
        $form = HostedForm::withoutGlobalScopes()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $form->load('campaign');

        $this->embedService->assertSubmitRefererAllowed($form, $request);

        $tracking = $this->embedService->resolveTracking($form, $request, $request->only(HostedFormEmbedService::TRACKING_QUERY_PARAMS));

        $lead = app(LeadIngestService::class)->ingest([
            'campaign_reference' => $form->campaign->reference,
            'source' => 'hosted_form:'.$form->slug,
            'embed' => $request->boolean('embed') || $request->query('embed') ? '1' : null,
            ...$tracking,
            ...$request->except(['_token', ...HostedFormEmbedService::TRACKING_QUERY_PARAMS, 'embed']),
        ]);

        ProcessLeadJob::dispatch($lead->id);

        $thankYou = $this->thankYouConfig($form);
        $redirectUrl = $form->config['redirect_url'] ?? null;

        if ($redirectUrl && ($thankYou['mode'] ?? 'inline') === 'redirect') {
            if ($request->header('X-Inertia')) {
                return Inertia::location($redirectUrl);
            }

            return redirect()->away($redirectUrl);
        }

        return Inertia::render('Forms/Show', array_merge($this->formPageProps($request, $slug), [
            'submitted' => true,
            'submission' => [
                'queue_id' => $lead->queue_id,
                'uuid' => $lead->uuid,
            ],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formPageProps(Request $request, string $slug): array
    {
        $form = HostedForm::withoutGlobalScopes()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $form->load('campaign.fields');

        $config = $form->config ?? [];
        $multiStep = ! empty($config['multi_step']) && ! empty($config['steps']);
        $steps = $multiStep ? $config['steps'] : [[
            'id' => 'single',
            'title' => $form->name,
            'fields' => $form->campaign->fields->map(fn ($f) => [
                'name' => $f->name,
                'label' => $f->label ?? $f->name,
                'type' => str_contains($f->name, 'email') ? 'email' : (str_contains($f->name, 'phone') ? 'tel' : 'text'),
                'required' => $f->required,
                'options' => $f->validation['enum'] ?? [],
            ])->values()->all(),
        ]];

        $embed = $request->boolean('embed');
        $tracking = $this->embedService->resolveTracking($form, $request);

        return [
            'form' => $form->only(['id', 'name', 'slug']),
            'steps' => $steps,
            'multiStep' => $multiStep,
            'submitUrl' => route('forms.submit', $form->slug),
            'thankYou' => $this->thankYouConfig($form),
            'submitted' => false,
            'submission' => null,
            'embed' => $embed,
            'tracking' => $tracking,
            'trackingParams' => HostedFormEmbedService::TRACKING_QUERY_PARAMS,
        ];
    }

    protected function thankYouConfig(HostedForm $form): array
    {
        $defaults = [
            'mode' => 'inline',
            'title' => 'Thank you!',
            'message' => 'Your enquiry has been received. We will be in touch shortly.',
            'show_reference' => true,
            'button_text' => 'Submit another response',
            'confetti' => true,
        ];

        return array_merge($defaults, $form->config['thank_you'] ?? []);
    }
}
